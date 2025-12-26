<?php

declare(strict_types=1);

namespace Climactic\Workspaces\ContextResolvers;

use Climactic\Workspaces\Contracts\ContextResolverContract;
use Climactic\Workspaces\Contracts\WorkspaceContract;
use Illuminate\Http\Request;

/**
 * Chains multiple resolvers together, trying each in order until one succeeds.
 */
class ChainResolver implements ContextResolverContract
{
    /**
     * The resolvers to try.
     *
     * @var array<ContextResolverContract>
     */
    protected array $resolvers = [];

    /**
     * Create a new chain resolver.
     *
     * @param  array<class-string<ContextResolverContract>|ContextResolverContract>  $resolvers
     */
    public function __construct(array $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->resolvers[] = is_string($resolver)
                ? app($resolver)
                : $resolver;
        }
    }

    /**
     * Resolve the current workspace from the request.
     *
     * @return (WorkspaceContract&\Illuminate\Database\Eloquent\Model)|null
     */
    public function resolve(Request $request): ?WorkspaceContract
    {
        foreach ($this->resolvers as $resolver) {
            $workspace = $resolver->resolve($request);

            if ($workspace) {
                return $workspace;
            }
        }

        return null;
    }

    /**
     * Add a resolver to the chain.
     */
    public function addResolver(ContextResolverContract|string $resolver): static
    {
        $this->resolvers[] = is_string($resolver)
            ? app($resolver)
            : $resolver;

        return $this;
    }

    /**
     * Prepend a resolver to the chain.
     */
    public function prependResolver(ContextResolverContract|string $resolver): static
    {
        array_unshift(
            $this->resolvers,
            is_string($resolver) ? app($resolver) : $resolver
        );

        return $this;
    }

    /**
     * Get all resolvers in the chain.
     *
     * @return array<ContextResolverContract>
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }

    /**
     * Create a chain resolver from the configured resolvers.
     */
    public static function fromConfig(): self
    {
        $resolvers = config('workspaces.context.resolvers', []);

        return new self($resolvers);
    }
}
