<?php

declare(strict_types=1);

namespace Vendor\LaravelAuthentication\Tests\Security;

use Illuminate\Database\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use Vendor\LaravelAuthentication\Tests\Fixtures\User;
use Vendor\LaravelAuthentication\Tests\TestCase;

/**
 * PR-05: concurrent OAuth callbacks for the same new email must not create two accounts.
 *
 * SocialAuthService resolved the user by email, and on a miss built a model and
 * called save(). Two callbacks for the same brand-new address both resolved null,
 * both attempted the insert, and the loser raised a unique violation that surfaced
 * as a failed login even though the account had in fact been created.
 *
 * The fix uses createOrFirst(): insert first, then re-read the committed winner via
 * the write PDO. These tests assert the UNIQUE(email) index remains the arbiter and
 * that exactly one account can exist per email.
 */
final class SocialLoginCreateRaceTest extends TestCase
{
    #[Test]
    public function the_email_column_arbiter_rejects_a_duplicate_oauth_registration(): void
    {
        $attributes = [
            'name'     => 'OAuth First',
            'email'    => 'oauth-race@example.test',
            'password' => bcrypt('RandomPass123!'),
        ];

        User::create($attributes);

        $raised = false;

        try {
            User::create($attributes);
        } catch (UniqueConstraintViolationException) {
            $raised = true;
        }

        $this->assertTrue(
            $raised,
            'UNIQUE(email) must reject a second account for the same address, otherwise a '
            . 'concurrent social callback yields two login identities'
        );

        $this->assertSame(
            1,
            User::where('email', 'oauth-race@example.test')->count(),
            'a create race must converge on exactly one account'
        );
    }

    #[Test]
    public function create_or_first_returns_the_existing_winner_instead_of_raising(): void
    {
        $existing = User::create([
            'name'     => 'OAuth Winner',
            'email'    => 'oauth-winner@example.test',
            'password' => bcrypt('WinnerPass123!'),
        ]);

        // Mirrors what SocialAuthService now does on a losing insert race.
        $winner = User::query()->createOrFirst(
            ['email' => 'oauth-winner@example.test'],
            ['name' => 'OAuth Loser', 'password' => bcrypt('LoserPass123!')]
        );

        $this->assertSame(
            (int) $existing->id,
            (int) $winner->id,
            'the loser of the race must adopt the existing account, not create a second one'
        );

        $this->assertSame(
            'OAuth Winner',
            $winner->name,
            'the existing account must not be overwritten by the losing callback'
        );

        $this->assertTrue(
            $winner->getAuthPassword() === $existing->getAuthPassword(),
            'the existing credential must be preserved'
        );
    }
}