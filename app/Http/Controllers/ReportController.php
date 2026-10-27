<?php

namespace App\Http\Controllers;

use App\Support\TeamReport;
use App\Support\WorkingHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports for supervisors: the team's replies and closes, member by member,
 * what customers wrote about and when, and how campaigns did, for a period
 * of days in the team's time zone. Each table downloads as CSV.
 */
class ReportController extends Controller
{
    private const int LONGEST_PERIOD_DAYS = 366;

    public function index(Request $request): Response
    {
        [$first, $last, $preset] = $this->period($request);
        $report = TeamReport::forDays($first, $last, WorkingHours::current()->timezone);

        return Inertia::render('reports/index', [
            'period' => ['from' => $first, 'to' => $last, 'preset' => $preset],
            'overview' => $report->overview(),
            'members' => $report->members(),
            'tags' => $report->tags(),
            'hours' => $report->hours(),
            'days' => $report->days(),
            'campaigns' => $report->campaigns(),
            'targetMinutes' => (int) config('inbox.long_wait_minutes'),
        ]);
    }

    public function export(Request $request, string $section): StreamedResponse
    {
        abort_unless(in_array($section, ['members', 'tags', 'hours', 'days', 'campaigns'], true), 404);

        [$first, $last] = $this->period($request);
        $report = TeamReport::forDays($first, $last, WorkingHours::current()->timezone);
        $label = fn (string $key): string => __("app.reports.export.{$key}");

        [$headings, $rows] = match ($section) {
            'members' => [
                [$label('member'), $label('replies'), $label('conversations'), $label('average_reply_minutes'), $label('median_reply_minutes'), $label('closed'), $label('average_close_minutes'), $label('open_now')],
                array_map(fn (array $row): array => [
                    $row['name'], $row['messages'], $row['conversations'], self::minutes($row['average_reply']),
                    self::minutes($row['median_reply']), $row['closed'], self::minutes($row['average_close']), $row['open_now'],
                ], $report->members()),
            ],
            'tags' => [
                [$label('tag'), $label('customers')],
                [...array_map(fn (array $tag): array => [$tag['name'], $tag['customers']], $report->tags()['tags']),
                    [$label('untagged'), $report->tags()['untagged']]],
            ],
            'hours' => [
                [$label('day'), ...range(0, 23)],
                array_map(fn (array $hours, int $day): array => [__("app.team.hours.days.{$day}"), ...$hours], $report->hours(), array_keys($report->hours())),
            ],
            'days' => [
                [$label('date'), $label('inbound'), $label('replies')],
                array_map(fn (array $day): array => [$day['day'], $day['inbound'], $day['replies']], $report->days()),
            ],
            'campaigns' => [
                [$label('campaign'), $label('template'), $label('started'), $label('recipients'), $label('sent'), $label('delivered'), $label('read'), $label('replied'), $label('failed'), $label('skipped')],
                array_map(fn (array $campaign): array => [
                    $campaign['name'], $campaign['template'], $campaign['started_at'], $campaign['stats']['total'], $campaign['stats']['sent'],
                    $campaign['stats']['delivered'], $campaign['stats']['read'], $campaign['stats']['replied'], $campaign['stats']['failed'], $campaign['stats']['skipped'],
                ], $report->campaigns()),
            ],
        };

        return response()->streamDownload(function () use ($headings, $rows): void {
            $out = fopen('php://output', 'w');
            // Excel reads Arabic correctly only with the byte order mark.
            fwrite($out, "\u{FEFF}");
            fputcsv($out, $headings, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '');
            }

            fclose($out);
        }, "mirsalink-{$section}-{$first}-{$last}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The days asked for: a preset (today, the last 7 or 30 days), or a
     * range of up to a year; the last 7 days when none is given.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function period(Request $request): array
    {
        $today = CarbonImmutable::now(WorkingHours::current()->timezone)->startOfDay();
        $preset = (string) $request->query('preset', 'week');

        if ($preset === 'custom') {
            $validated = $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $from = CarbonImmutable::parse($validated['from']);
            $to = CarbonImmutable::parse($validated['to']);

            if ($from->diffInDays($to) < self::LONGEST_PERIOD_DAYS) {
                return [$from->toDateString(), $to->toDateString(), 'custom'];
            }
        }

        $days = match ($preset) {
            'today' => 0,
            'month' => 29,
            default => 6,
        };

        return [$today->subDays($days)->toDateString(), $today->toDateString(), in_array($preset, ['today', 'month'], true) ? $preset : 'week'];
    }

    private static function minutes(?int $seconds): ?string
    {
        return $seconds === null ? null : number_format($seconds / 60, 1, '.', '');
    }
}
