<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resolves workspace from the request subdomain.
 */
class SubdomainResolver extends ContextResolver
{
    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        $host = $request->getHost();
        $baseDomain = config('workspaces.context.subdomain.domain');

        if (! $baseDomain) {
            return null;
        }

        // Extract subdomain
        $subdomain = $this->extractSubdomain($host, $baseDomain);

        if (! $subdomain) {
            return null;
        }

        return $this->findBy('slug', $subdomain);
    }

    /**
     * Extract the subdomain from the host.
     */
    protected function extractSubdomain(string $host, string $baseDomain): ?string
    {
        // Remove port if present
        $host = preg_replace('/:\d+$/', '', $host);

        // Check if the host ends with the base domain
        if (! str_ends_with($host, $baseDomain)) {
            return null;
        }

        // Extract the subdomain
        $subdomain = rtrim(str_replace($baseDomain, '', $host), '.');

        if (empty($subdomain) || $subdomain === $host) {
            return null;
        }

        // Validate subdomain format to prevent injection attacks
        // Only allow alphanumeric characters, hyphens, and underscores
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $subdomain)) {
            return null;
        }

        return $subdomain;
    }
}
