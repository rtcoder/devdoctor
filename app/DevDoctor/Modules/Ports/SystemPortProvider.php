<?php

declare(strict_types=1);

namespace App\DevDoctor\Modules\Ports;

final readonly class SystemPortProvider implements PortProviderInterface
{
    /**
     * @param list<PortProviderInterface>|null $providers
     */
    public function __construct(
        private ?array $providers = null,
    )
    {
    }

    public function available(): bool
    {
        return $this->provider() instanceof PortProviderInterface;
    }

    public function usages(int $port): array
    {
        return $this->provider()?->usages($port) ?? [];
    }

    private function provider(): ?PortProviderInterface
    {
        foreach ($this->providers() as $provider) {
            if ($provider->available()) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @return list<PortProviderInterface>
     */
    private function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return [new WindowsNetstatPortProvider];
        }

        return [
            new LsofPortProvider,
            new SsPortProvider,
        ];
    }
}
