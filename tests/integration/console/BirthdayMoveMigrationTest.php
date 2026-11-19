<?php

namespace ErnestDefoe\Calendar\Tests\integration\console;

use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\User\User;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

/**
 * A forum upgrading from Calendar 2.8 has birthdays in users.cal_birthday:
 * the migration moves each one into calendar_birthdays and drops the column.
 *
 * Ends migrated, as it started: MySQL commits schema changes whatever
 * transaction the test runs in.
 */
class BirthdayMoveMigrationTest extends ConsoleTestCase
{
    private const MIGRATION = '2026_11_19_000001_move_birthdays_off_users';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ernestdefoe-calendar');

        $this->prepareDatabase([
            User::class => [
                ['id' => 2, 'username' => 'july', 'email' => 'july@machine.local', 'is_email_confirmed' => 1],
                ['id' => 3, 'username' => 'nobirthday', 'email' => 'nobirthday@machine.local', 'is_email_confirmed' => 1],
            ],
        ]);
    }

    #[Test]
    public function birthdays_move_off_the_users_table()
    {
        $this->app();
        $schema = $this->database()->getSchemaBuilder();

        // The state Calendar 2.8 left: the column on users, holding a birthday.
        $schema->table('users', fn (Blueprint $table) => $table->string('cal_birthday', 5)->nullable());
        $this->database()->table('users')->where('id', 2)->update(['cal_birthday' => '07-04']);
        $this->database()->table('migrations')->where('migration', self::MIGRATION)->delete();

        $this->runCommand(['command' => 'migrate']);

        $this->assertFalse($schema->hasColumn('users', 'cal_birthday'), 'The column is gone from core\'s table');
        $this->assertSame(
            [[2, '07-04']],
            $this->database()->table('calendar_birthdays')->orderBy('user_id')->get()
                ->map(fn ($row) => [(int) $row->user_id, $row->birthday])->all()
        );
    }
}
