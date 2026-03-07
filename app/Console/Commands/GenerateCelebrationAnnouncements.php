<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateCelebrationAnnouncements extends Command
{
    protected $signature = 'announcements:generate-celebrations {--date= : Override date (Y-m-d) for testing}';

    protected $description = 'Auto-create Birthday and Work Anniversary announcements for today\'s celebrants';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();

        $month = $today->month;
        $day = $today->day;

        $this->info("Running celebration check for {$today->toDateString()}...");

        $this->generateBirthdays($today, $month, $day);
        $this->generateAnniversaries($today, $month, $day);

        $this->info('Done.');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------
    // Birthdays
    // ---------------------------------------------------------------
    private function generateBirthdays(Carbon $today, int $month, int $day): void
    {
        $celebrants = DB::table('tbluser_profile')
            ->whereNotNull('birthdate')
            ->whereMonth('birthdate', $month)
            ->whereDay('birthdate', $day)
            ->get(['user_id', 'full_name']);

        if ($celebrants->isEmpty()) {
            $this->line('  No birthday celebrants today.');

            return;
        }

        $names = $celebrants->pluck('full_name')->filter()->implode(', ');
        $count = $celebrants->count();

        $title = $count === 1
            ? "🎂 Happy Birthday, {$names}!"
            : "🎂 Happy Birthday to our {$count} celebrants!";

        $content = $count === 1
            ? "Today is {$names}'s birthday! 🎉 Wishing you a wonderful day filled with joy and happiness!"
            : "Today we celebrate birthdays! 🎉 Let's give a warm shoutout to: {$names}. Wishing you all a fantastic day!";

        $this->upsertAnnouncement('birthday', $today, $title, $content);
        $this->info("  ✅ Birthday announcement created for: {$names}");
    }

    // ---------------------------------------------------------------
    // Work Anniversaries
    // ---------------------------------------------------------------
    private function generateAnniversaries(Carbon $today, int $month, int $day): void
    {
        $celebrants = DB::table('tbluser_profile')
            ->whereNotNull('hire_date')
            ->whereMonth('hire_date', $month)
            ->whereDay('hire_date', $day)
            ->where('hire_date', '<', $today->toDateString())
            ->get(['user_id', 'full_name', 'hire_date']);

        if ($celebrants->isEmpty()) {
            $this->line('  No work anniversaries today.');

            return;
        }

        $lines = $celebrants->map(function ($emp) use ($today) {
            $years = Carbon::parse($emp->hire_date)->diffInYears($today);
            $label = $years === 1 ? '1 year' : "{$years} years";

            return "{$emp->full_name} ({$label})";
        });

        $count = $celebrants->count();
        $nameStr = $lines->implode(', ');

        $title = $count === 1
            ? "🏆 Work Anniversary: {$celebrants->first()->full_name}!"
            : '🏆 Work Anniversaries Today!';

        $content = "Congratulations to our team member(s) celebrating their work anniversary today! 🎊\n{$nameStr}\nThank you for your dedication and hard work!";

        $this->upsertAnnouncement('anniversary', $today, $title, $content);
        $this->info("  ✅ Anniversary announcement created for: {$nameStr}");
    }

    // ---------------------------------------------------------------
    // Upsert — skips if already created today for this type
    // ---------------------------------------------------------------
    private function upsertAnnouncement(
        string $type,
        Carbon $today,
        string $title,
        string $content
    ): void {
        $exists = Announcement::where('type', $type)
            ->whereDate('auto_date', $today->toDateString())
            ->exists();

        if ($exists) {
            $this->line("  ⚠️  {$type} announcement already exists for today — skipping.");

            return;
        }

        Announcement::create([
            'title' => $title,
            'content' => $content,
            'start_at' => $today->copy()->startOfDay(),
            'end_at' => $today->copy()->endOfDay(),
            'is_active' => true,
            'priority' => 0,
            'recipients_json' => null,
            'readby' => [],
            'type' => $type,
            'auto_date' => $today->toDateString(),
            'created_by' => 'system',
        ]);
    }
}
