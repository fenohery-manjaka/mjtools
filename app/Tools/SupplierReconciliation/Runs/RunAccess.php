<?php

namespace App\Tools\SupplierReconciliation\Runs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No account is needed for the free checker: a run belongs to the browser
 * that created it, through a random token.
 *
 * The token lives in the session and in a dedicated encrypted, HttpOnly
 * cookie that lasts as long as the data is retained, so that a run is not
 * lost when the session expires before its retention period ends.
 */
final class RunAccess
{
    private const SESSION_KEY = 'supplier_reconciliation.owner_token';

    public const COOKIE = 'mjtools_supplier_reconciliation';

    public function create(Request $request): ReconciliationRun
    {
        $hours = max(1, (int) config('supplier-reconciliation.retention_hours'));
        $token = $this->token($request) ?? Str::random(64);

        $request->session()->put(self::SESSION_KEY, $token);
        Cookie::queue(Cookie::make(self::COOKIE, $token, $hours * 60, httpOnly: true, sameSite: 'lax'));

        $run = new ReconciliationRun;
        $run->owner_token_hash = $this->hash($token);
        $run->expires_at = now()->addHours($hours)->toImmutable();
        $run->save();

        return $run;
    }

    /**
     * Runs of other browsers, and expired runs, simply do not exist.
     */
    public function ensure(Request $request, ReconciliationRun $run): void
    {
        $owned = false;

        foreach ($this->tokens($request) as $token) {
            $owned = $owned || hash_equals($run->owner_token_hash, $this->hash($token));
        }

        if (! $owned || $run->isExpired()) {
            throw new NotFoundHttpException;
        }
    }

    private function token(Request $request): ?string
    {
        return $this->tokens($request)[0] ?? null;
    }

    /**
     * @return list<string> Session token first, then the cookie one.
     */
    private function tokens(Request $request): array
    {
        return array_values(array_unique(array_filter(
            [$request->session()->get(self::SESSION_KEY), $request->cookie(self::COOKIE)],
            fn (mixed $token): bool => is_string($token) && strlen($token) >= 40,
        )));
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
