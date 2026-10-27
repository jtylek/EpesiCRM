<?php

namespace Epesi\Modules\OAuth\Contracts;

use App\Models\User;

/**
 * A model that can own OAuth tokens: a mail account, or a business record
 * such as a sales channel or an integration's settings row. Implemented
 * together with the HasOAuthTokens trait, under a registered morph alias.
 *
 * mayConnectOAuth() is the owner's own rule, so a personal owner (a mailbox)
 * can refuse everyone but its user while a business owner lets, say, any
 * super admin connect it (or asks its policy). It is not a policy ability on
 * purpose: Shield's Gate::before lets a super admin past every policy.
 *
 * Who is told when the token needs connecting again: OAuthRecipients if the
 * owner implements it, otherwise every super admin.
 */
interface OAuthOwner
{
    /** Whether $user may connect, reconnect or disconnect this owner's tokens. */
    public function mayConnectOAuth(User $user): bool;

    /** The owner in a sentence: "the mail account Work". */
    public function oauthLabel(): string;

    /** The account to suggest on the provider's sign-in page (login_hint), if known. */
    public function oauthLoginHint(): ?string;

    /** Where to connect it again, for notifications; null for no link. */
    public function oauthUrl(): ?string;
}
