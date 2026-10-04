<?php

namespace CTRLServers\AdminExtension\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

class PanelSettingsService
{
    public function __construct(
        private ConfigRepository $config,
        private Encrypter $encrypter,
        private Kernel $kernel,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    public function update(array $values, array $encrypted = []): void
    {
        foreach ($values as $key => $value) {
            $storedvalue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
            if (in_array($key, $encrypted, true) && $value !== null && $value !== '') {
                $storedvalue = $this->encrypter->encrypt($value);
            }

            $this->settings->set('settings::' . $key, $storedvalue);
            $this->config->set(str_replace(':', '.', $key), $value);
        }

        $this->kernel->call('queue:restart');
    }
}
