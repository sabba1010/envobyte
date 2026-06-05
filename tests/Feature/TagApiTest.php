<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Tag;
use App\Models\Vault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test creating a tag and verifying it appears in the tag list.
     */
    public function test_creating_a_tag_and_verifying_it_appears_in_the_tag_list()
    {
        $user = $this->createUser(['read', 'write']);
        $vault = $this->createVaultUser($user, Vault::PERMISSION_EDIT);

        $response = $this->postJson('/api/tags', [
            'name' => 'Colleague',
            'vault_id' => $vault->id,
            'tag_category' => 'Work',
            'color' => 'blue',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Colleague');
        $response->assertJsonPath('data.tag_category', 'Work');
        $response->assertJsonPath('data.color', 'blue');

        $listResponse = $this->getJson('/api/tags');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonFragment([
            'name' => 'Colleague',
            'tag_category' => 'Work',
            'color' => 'blue',
        ]);
    }

    /**
     * Test attaching two tags to a contact and filtering by both tags.
     */
    public function test_attaching_two_tags_to_a_contact_and_filtering_by_both_tags()
    {
        $user = $this->createUser(['read', 'write']);
        $vault = $this->createVaultUser($user, Vault::PERMISSION_EDIT);

        $tag1 = Tag::create([
            'vault_id' => $vault->id,
            'name' => 'Colleague',
            'slug' => 'colleague',
        ]);
        $tag2 = Tag::create([
            'vault_id' => $vault->id,
            'name' => 'Friend',
            'slug' => 'friend',
        ]);

        $contact1 = Contact::factory()->create(['vault_id' => $vault->id]);
        $contact2 = Contact::factory()->create(['vault_id' => $vault->id]);

        // Attach tag1 and tag2 to contact1
        $this->postJson("/api/contacts/{$contact1->id}/tags", [
            'tag_ids' => [$tag1->id, $tag2->id]
        ])->assertStatus(200);

        // Attach only tag1 to contact2
        $this->postJson("/api/contacts/{$contact2->id}/tags", [
            'tag_ids' => [$tag1->id]
        ])->assertStatus(200);

        // Filter contacts by tag1 AND tag2 (should only return contact1)
        $filterResponse = $this->getJson("/api/contacts?tags[]={$tag1->id}&tags[]={$tag2->id}");
        $filterResponse->assertStatus(200);

        $filterResponse->assertJsonCount(1, 'data');
        $filterResponse->assertJsonPath('data.0.id', $contact1->id);
    }

    /**
     * Test deleting a tag that is attached to contacts (verify detach behavior).
     */
    public function test_deleting_a_tag_that_is_attached_to_contacts()
    {
        $user = $this->createUser(['read', 'write']);
        $vault = $this->createVaultUser($user, Vault::PERMISSION_EDIT);

        $tag = Tag::create([
            'vault_id' => $vault->id,
            'name' => 'Temporary',
            'slug' => 'temporary',
        ]);

        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $contact->tags()->attach($tag->id);

        $this->assertTrue($contact->tags()->where('tags.id', $tag->id)->exists());

        // Delete the tag
        $response = $this->deleteJson("/api/tags/{$tag->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        // Verify tag is detached from contact
        $this->assertFalse($contact->tags()->where('tags.id', $tag->id)->exists());
    }

    /**
     * Test deleting a tag with reassignment.
     */
    public function test_deleting_a_tag_with_reassignment()
    {
        $user = $this->createUser(['read', 'write']);
        $vault = $this->createVaultUser($user, Vault::PERMISSION_EDIT);

        $oldTag = Tag::create([
            'vault_id' => $vault->id,
            'name' => 'Old Tag',
            'slug' => 'old-tag',
        ]);

        $newTag = Tag::create([
            'vault_id' => $vault->id,
            'name' => 'New Tag',
            'slug' => 'new-tag',
        ]);

        $contact = Contact::factory()->create(['vault_id' => $vault->id]);
        $contact->tags()->attach($oldTag->id);

        $this->assertTrue($contact->tags()->where('tags.id', $oldTag->id)->exists());
        $this->assertFalse($contact->tags()->where('tags.id', $newTag->id)->exists());

        // Delete old tag and reassign to new tag
        $response = $this->deleteJson("/api/tags/{$oldTag->id}", [
            'reassign_tag_id' => $newTag->id,
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseMissing('tags', ['id' => $oldTag->id]);

        // Verify old tag is detached, and new tag is attached
        $this->assertFalse($contact->tags()->where('tags.id', $oldTag->id)->exists());
        $this->assertTrue($contact->tags()->where('tags.id', $newTag->id)->exists());
    }

    /**
     * Test cache invalidation on tag creation.
     */
    public function test_cache_invalidation_on_tag_creation()
    {
        $user = $this->createUser(['read', 'write']);
        $vault = $this->createVaultUser($user, Vault::PERMISSION_EDIT);
        $cacheKey = "tags:account:{$user->account_id}";

        // Initial request to populate cache
        $this->getJson('/api/tags')->assertStatus(200);

        // Verify cache exists
        $this->assertTrue(Cache::has($cacheKey));

        // Create a tag
        $this->postJson('/api/tags', [
            'name' => 'Cached Tag',
            'vault_id' => $vault->id,
        ])->assertStatus(201);

        // Verify cache has been invalidated (removed)
        $this->assertFalse(Cache::has($cacheKey));
    }
}
