<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TagApiController extends ApiController
{
    public function __construct()
    {
        $this->middleware('abilities:read')->only(['index']);
        $this->middleware('abilities:write')->only(['store', 'update', 'destroy']);

        parent::__construct();
    }

    /**
     * List all tags for the account.
     */
    public function index(Request $request)
    {
        $accountId = $request->user()->account_id;
        $cacheKey = "tags:account:{$accountId}";

        $tags = Cache::remember($cacheKey, 600, function () use ($request) {
            $vaultIds = $request->user()->account->vaults()->pluck('id');
            return Tag::whereIn('vault_id', $vaultIds)
                ->withCount('contacts')
                ->get();
        });

        return TagResource::collection($tags);
    }

    /**
     * Create a new tag.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'vault_id' => 'nullable|uuid|exists:vaults,id',
            'tag_category' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
        ]);

        $vaultId = $request->input('vault_id');
        if (!$vaultId) {
            $vault = $request->user()->account->vaults()->first();
            if (!$vault) {
                return $this->respondInvalidParameters('Account has no vaults.');
            }
            $vaultId = $vault->id;
        } else {
            $vault = $request->user()->account->vaults()->where('id', $vaultId)->first();
            if (!$vault) {
                return $this->respondUnauthorized('Unauthorized vault access.');
            }
        }

        $tag = Tag::create([
            'vault_id' => $vaultId,
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name'), '-', language: currentLang()),
            'tag_category' => $request->input('tag_category'),
            'color' => $request->input('color'),
        ]);

        // Invalidate Cache
        Cache::forget('tags:account:' . $request->user()->account_id);

        return new TagResource($tag);
    }

    /**
     * Update a tag.
     */
    public function update(Request $request, $id)
    {
        $vaultIds = $request->user()->account->vaults()->pluck('id');
        $tag = Tag::whereIn('vault_id', $vaultIds)->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'tag_category' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
        ]);

        $tag->update([
            'name' => $request->input('name'),
            'slug' => Str::slug($request->input('name'), '-', language: currentLang()),
            'tag_category' => $request->input('tag_category'),
            'color' => $request->input('color'),
        ]);

        // Invalidate Cache
        Cache::forget('tags:account:' . $request->user()->account_id);

        return new TagResource($tag);
    }

    /**
     * Delete a tag.
     */
    public function destroy(Request $request, $id)
    {
        $vaultIds = $request->user()->account->vaults()->pluck('id');
        $tag = Tag::whereIn('vault_id', $vaultIds)->findOrFail($id);

        $reassignTagId = $request->input('reassign_tag_id');
        if ($reassignTagId) {
            $newTag = Tag::whereIn('vault_id', $vaultIds)->findOrFail($reassignTagId);

            // Reassign contacts tagged with the old tag to the new tag.
            $contactIds = $tag->contacts()->pluck('contacts.id');

            foreach ($contactIds as $contactId) {
                $contact = \App\Models\Contact::find($contactId);
                if ($contact) {
                    $contact->tags()->syncWithoutDetaching([$newTag->id]);
                }
            }
        }

        $tag->delete();

        // Invalidate Cache
        Cache::forget('tags:account:' . $request->user()->account_id);

        return $this->respondObjectDeleted($id);
    }
}
