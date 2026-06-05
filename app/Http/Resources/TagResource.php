<?php

namespace App\Http\Resources;

use App\Helpers\DateHelper;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tag
 */
class TagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'vault_id' => $this->vault_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tag_category' => $this->tag_category,
            'color' => $this->color,
            'usage_count' => $this->relationLoaded('contacts') || isset($this->contacts_count) ? (int) ($this->contacts_count ?? $this->contacts()->count()) : 0,
            'created_at' => DateHelper::getTimestamp($this->created_at),
            'updated_at' => DateHelper::getTimestamp($this->updated_at),
        ];
    }
}
