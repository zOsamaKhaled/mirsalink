<?php

namespace App\Enums;

/**
 * The kinds WhatsApp messages come in, plus the internal note, which is
 * never sent anywhere.
 */
enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
    case Audio = 'audio';
    case Video = 'video';
    case Location = 'location';
    case Template = 'template';
    case Note = 'note';

    public function hasMedia(): bool
    {
        return in_array($this, [self::Image, self::Document, self::Audio, self::Video], true);
    }

    /**
     * The kind an uploaded file is sent as, by its MIME type.
     */
    public static function forMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'audio/') => self::Audio,
            str_starts_with($mime, 'video/') => self::Video,
            default => self::Document,
        };
    }
}
