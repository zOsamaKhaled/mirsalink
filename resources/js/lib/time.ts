/*
 * Dates and times in the member's language, always with Latin digits and
 * the Gregorian calendar, as the design system asks.
 */

function localeTag(locale: string): string {
    return `${locale}-u-nu-latn-ca-gregory`;
}

function sameDay(a: Date, b: Date): boolean {
    return (
        a.getFullYear() === b.getFullYear() &&
        a.getMonth() === b.getMonth() &&
        a.getDate() === b.getDate()
    );
}

function daysAgo(date: Date, now: Date = new Date()): number {
    const start = (d: Date) =>
        new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();

    return Math.round((start(now) - start(date)) / 86_400_000);
}

/** 14:05 */
export function formatTime(iso: string, locale: string): string {
    return new Intl.DateTimeFormat(localeTag(locale), {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).format(new Date(iso));
}

/**
 * For the conversation list: the time today, "Yesterday", the weekday
 * within a week, the date after that.
 */
export function formatListTime(
    iso: string,
    locale: string,
    yesterday: string,
): string {
    const date = new Date(iso);
    const ago = daysAgo(date);

    if (ago <= 0) {
        return formatTime(iso, locale);
    }

    if (ago === 1) {
        return yesterday;
    }

    if (ago < 7) {
        return new Intl.DateTimeFormat(localeTag(locale), {
            weekday: 'long',
        }).format(date);
    }

    return new Intl.DateTimeFormat(localeTag(locale), {
        day: 'numeric',
        month: 'numeric',
        year: '2-digit',
    }).format(date);
}

/** The separator between days in a thread: Today, Yesterday, or the date. */
export function formatDay(
    iso: string,
    locale: string,
    labels: { today: string; yesterday: string },
): string {
    const date = new Date(iso);

    if (sameDay(date, new Date())) {
        return labels.today;
    }

    if (daysAgo(date) === 1) {
        return labels.yesterday;
    }

    return new Intl.DateTimeFormat(localeTag(locale), {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

/** 26 October 2026 */
export function formatDate(iso: string, locale: string): string {
    return new Intl.DateTimeFormat(localeTag(locale), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(iso));
}

/** Time left as hours and minutes, 21:05. */
export function formatHoursLeft(milliseconds: number): string {
    const minutes = Math.max(0, Math.floor(milliseconds / 60_000));
    const hours = Math.floor(minutes / 60);

    return `${hours}:${String(minutes % 60).padStart(2, '0')}`;
}

export function isSameDay(a: string, b: string): boolean {
    return sameDay(new Date(a), new Date(b));
}

/** 1.2 MB, for attachments. */
export function formatFileSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * A length of time in its two largest units, such as 45 s, 12 m, 1 h 05 m
 * or 2 d 3 h; the unit letters come from the member's language.
 */
export function formatDuration(
    seconds: number,
    units: { s: string; m: string; h: string; d: string },
): string {
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (seconds < 60) {
        return `${Math.max(0, Math.round(seconds))} ${units.s}`;
    }

    if (minutes < 60) {
        return `${minutes} ${units.m}`;
    }

    if (hours < 24) {
        return minutes % 60 === 0
            ? `${hours} ${units.h}`
            : `${hours} ${units.h} ${String(minutes % 60).padStart(2, '0')} ${units.m}`;
    }

    return hours % 24 === 0
        ? `${days} ${units.d}`
        : `${days} ${units.d} ${hours % 24} ${units.h}`;
}
