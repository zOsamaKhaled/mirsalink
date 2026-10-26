<?php

namespace App\Models;

use App\Enums\TemplateCategory;
use App\Enums\TemplateHeader;
use App\Enums\TemplateStatus;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A WhatsApp message template. Its text holds numbered variables, {{1}},
 * {{2}}…, filled in when it is sent; Meta reviews it with the example
 * value given for each.
 *
 * @property int $id
 * @property string $name
 * @property string $language
 * @property TemplateCategory $category
 * @property TemplateHeader $header_type
 * @property string|null $header_text
 * @property string|null $header_example
 * @property string $body
 * @property list<string>|null $body_examples
 * @property string|null $footer
 * @property list<array{type: string, text: string, url?: string|null, example?: string|null, phone?: string|null}>|null $buttons
 * @property TemplateStatus $status
 * @property string|null $rejection_reason
 * @property string|null $external_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    public const array LANGUAGES = ['ar', 'en'];

    public const string VARIABLE = '/\{\{\s*(\d+)\s*\}\}/';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TemplateCategory::class,
            'header_type' => TemplateHeader::class,
            'status' => TemplateStatus::class,
            'body_examples' => 'array',
            'buttons' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isApproved(): bool
    {
        return $this->status === TemplateStatus::Approved;
    }

    /**
     * The numbers of the variables in a text, in the order they appear.
     *
     * @return list<int>
     */
    public static function variablesIn(?string $text): array
    {
        preg_match_all(self::VARIABLE, (string) $text, $matches);

        return array_map('intval', $matches[1]);
    }

    public function bodyVariableCount(): int
    {
        return count(array_unique(self::variablesIn($this->body)));
    }

    public function headerHasVariable(): bool
    {
        return $this->header_type === TemplateHeader::Text && self::variablesIn($this->header_text) !== [];
    }

    /**
     * Positions of the link buttons whose address ends in a variable.
     *
     * @return list<int>
     */
    public function linkButtonsWithVariable(): array
    {
        $positions = [];

        foreach ($this->buttons ?? [] as $position => $button) {
            if ($button['type'] === 'url' && self::variablesIn($button['url'] ?? null) !== []) {
                $positions[] = $position;
            }
        }

        return $positions;
    }

    /**
     * Puts the values in place of {{1}}, {{2}}…; a missing value leaves
     * its variable showing.
     *
     * @param  list<string>  $values
     */
    public static function withValues(?string $text, array $values): string
    {
        return (string) preg_replace_callback(
            self::VARIABLE,
            fn (array $match): string => $values[(int) $match[1] - 1] ?? $match[0],
            (string) $text,
        );
    }
}
