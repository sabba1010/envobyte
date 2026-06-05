<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactApiController extends ApiController
{
    public function __construct()
    {
        $this->middleware('abilities:read')->only(['index']);
        $this->middleware('abilities:write')->only(['attachTags', 'detachTag']);

        parent::__construct();
    }

    /**
     * List all contacts, optionally filtered by tags.
     */
    public function index(Request $request)
    {
        $vaultIds = $request->user()->account->vaults()->pluck('id');
        $query = Contact::whereIn('vault_id', $vaultIds);

        $tagIds = $request->input('tags', []);
        if (!is_array($tagIds)) {
            $tagIds = [$tagIds];
        }

        // Filter out empty or null values
        $tagIds = array_filter($tagIds, fn($val) => !is_null($val) && $val !== '');

        if (!empty($tagIds)) {
            // AND logic: contact must have ALL specified tags.
            // Under the hood, Laravel translates this loop of whereHas constraints into a single SQL statement.
            foreach ($tagIds as $tagId) {
                $query->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            }
        }

        $sort = $request->input('sort');
        if ($sort === 'name') {
            $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $query->with('tags');

        $contacts = $query->paginate($this->getLimitPerPage());

        return ContactResource::collection($contacts);
    }

    /**
     * Attach tags to a contact.
     */
    public function attachTags(Request $request, $id)
    {
        $vaultIds = $request->user()->account->vaults()->pluck('id');
        $contact = Contact::whereIn('vault_id', $vaultIds)->findOrFail($id);

        $request->validate([
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ]);

        $tagIds = $request->input('tag_ids');

        // Verify tags belong to the user's vaults
        $validTagsCount = Tag::whereIn('vault_id', $vaultIds)->whereIn('id', $tagIds)->count();
        if ($validTagsCount !== count(array_unique($tagIds))) {
            return $this->respondUnauthorized('One or more tag IDs do not belong to your account vaults.');
        }

        $contact->tags()->syncWithoutDetaching($tagIds);

        // Invalidate Cache
        Cache::forget('tags:account:' . $request->user()->account_id);

        $contact->load('tags');
        return new ContactResource($contact);
    }

    /**
     * Detach a tag from a contact.
     */
    public function detachTag(Request $request, $id, $tagId)
    {
        $vaultIds = $request->user()->account->vaults()->pluck('id');
        $contact = Contact::whereIn('vault_id', $vaultIds)->findOrFail($id);

        // Verify the tag belongs to the user's vaults
        Tag::whereIn('vault_id', $vaultIds)->findOrFail($tagId);

        $contact->tags()->detach($tagId);

        // Invalidate Cache
        Cache::forget('tags:account:' . $request->user()->account_id);

        $contact->load('tags');
        return new ContactResource($contact);
    }
}
