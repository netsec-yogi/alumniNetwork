<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use InvalidArgumentException;
use Laravel\Socialite\Contracts\User as ProviderUser;

/**
 * Sign-in with Google or LinkedIn (SRS 10). Deliberately conservative:
 * a provider identity signs in only to the account it was linked to while
 * signed in with a password. A matching email alone never grants access,
 * so taking over someone's mailbox provider doesn't take over the portal,
 * and new members still go through alumni verification at registration.
 */
class SocialLoginService
{
    /** Socialite driver name => label, for providers with credentials configured. */
    public static function enabled(): array
    {
        return collect(['google' => 'Google', 'linkedin-openid' => 'LinkedIn'])
            ->filter(fn ($label, $driver) => filled(config("services.{$driver}.client_id")) && filled(config("services.{$driver}.client_secret")))
            ->all();
    }

    public function __construct(private readonly AuditLogger $audit) {}

    public function findUser(string $provider, ProviderUser $identity): ?User
    {
        $account = SocialAccount::with('user')->where('provider', $provider)->where('provider_user_id', (string) $identity->getId())->first();
        $account?->forceFill(['last_used_at' => now()])->save();

        return $account?->user;
    }

    public function link(User $user, string $provider, ProviderUser $identity): SocialAccount
    {
        $existing = SocialAccount::where('provider', $provider)->where('provider_user_id', (string) $identity->getId())->first();
        if ($existing && $existing->user_id !== $user->id) {
            throw new InvalidArgumentException('That account is already linked to a different Alumni Connect member.');
        }

        $account = SocialAccount::where('user_id', $user->id)->where('provider', $provider)->first() ?? new SocialAccount;
        $account->forceFill(['user_id' => $user->id, 'provider' => $provider, 'provider_user_id' => (string) $identity->getId(), 'email' => $identity->getEmail()])->save();
        $this->audit->record('social.linked', 'security', $user, null, ['provider' => $provider], $user);

        return $account;
    }

    public function unlink(User $user, string $provider): void
    {
        if (SocialAccount::where('user_id', $user->id)->where('provider', $provider)->delete()) {
            $this->audit->record('social.unlinked', 'security', $user, ['provider' => $provider], null, $user);
        }
    }
}
