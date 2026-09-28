<?php

namespace App\Tools\SupplierReconciliation\Runs;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No account is needed for the free checker: a run belongs to the browser
 * session that created it, through a random token kept in the session.
 */
final class RunAccess
{
    private const SESSION_KEY = 'supplier_reconciliation.owner_token';

    public function create(Request $request): ReconciliationRun
    {
        $run = new ReconciliationRun;
        $run->owner_token_hash = $this->hash($this->token($request));
        $run->expires_at = now()->addHours(max(1, (int) config('supplier-reconciliation.retention_hours')))->toImmutable();
        $run->save();

        return $run;
    }

    /**
     * Runs of other sessions, and expired runs, simply do not exist.
     */
    public function ensure(Request $request, ReconciliationRun $run): void
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! is_string($token) || ! hash_equals($run->owner_token_hash, $this->hash($token)) || $run->isExpired()) {
            throw new NotFoundHttpException;
        }
    }

    private function token(Request $request): string
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! is_string($token) || strlen($token) < 40) {
            $token = Str::random(64);
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return $token;
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
