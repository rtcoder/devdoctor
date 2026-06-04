<?php

declare(strict_types=1);

namespace DevDoctor\Modules\Ports;

use DevDoctor\Core\Platform;

final readonly class SystemPortProvider implements PortProviderInterface
{
    /**
     * @param  list<PortProviderInterface>|null  $providers
     */
    public function __construct(
        private ?array $providers = null,
        private Platform $platform = Platform::OTHER,
    ) {}

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

        $platform = $this->platform === Platform::OTHER ? Platform::current() : $this->platform;

        if ($platform === Platform::WINDOWS) {
            return [new WindowsNetstatPortProvider];
        }

        if ($platform === Platform::LINUX) {
            return [new LsofPortProvider, new SsPortProvider];
        }

        return $platform === Platform::MACOS ? [new LsofPortProvider] : [];
    }
}
