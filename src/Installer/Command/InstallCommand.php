<?php

declare(strict_types=1);

namespace App\Installer\Command;

use App\Environment;
use App\Installer\EnvFiles\AbstractEnvFile;
use App\Installer\EnvFiles\PlayCastEnvFile;
use App\Installer\EnvFiles\EnvFile;
use App\Locale;
use App\Radio\Configuration;
use App\Utilities\Strings;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class InstallCommand
{
    public const DEFAULT_BASE_DIRECTORY = '/installer';

    public function __construct(
        protected Environment $environment
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $update,
        bool $defaults,
        ?int $httpPort = null,
        ?int $httpsPort = null,
        ?string $releaseChannel = null,
        string $baseDir = self::DEFAULT_BASE_DIRECTORY
    ): int {
        $devMode = ($baseDir !== self::DEFAULT_BASE_DIRECTORY);

        // Initialize all the environment variables.
        $envPath = EnvFile::buildPathFromBase($baseDir);
        $playcastEnvPath = PlayCastEnvFile::buildPathFromBase($baseDir);

        // Fail early if permissions aren't present.
        if (!is_writable($envPath)) {
            $io->error(
                'Permissions error: cannot write to work directory. Exiting installer and using defaults instead.'
            );
            return 1;
        }

        $isNewInstall = !$update;

        try {
            $env = EnvFile::fromEnvFile($envPath);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            $env = new EnvFile($envPath);
        }

        try {
            $playcastEnv = PlayCastEnvFile::fromEnvFile($playcastEnvPath);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            $playcastEnv = new PlayCastEnvFile($envPath);
        }

        // Initialize locale for translated installer/updater.
        if (!$defaults && ($isNewInstall || empty($playcastEnv[Environment::LANG]))) {
            $langOptions = [];
            foreach (Locale::SUPPORTED_LOCALES as $langKey => $langName) {
                $langOptions[Locale::stripLocaleEncoding($langKey)] = $langName;
            }

            $playcastEnv[Environment::LANG] = $io->choice(
                'Select Language',
                $langOptions,
                Locale::stripLocaleEncoding(Locale::DEFAULT_LOCALE)
            );
        }

        $locale = new Locale($this->environment, $playcastEnv[Environment::LANG] ?? Locale::DEFAULT_LOCALE);
        $locale->register();

        $envConfig = EnvFile::getConfiguration();
        $env->setFromDefaults();

        $playcastEnvConfig = PlayCastEnvFile::getConfiguration();
        $playcastEnv->setFromDefaults();

        // Apply values passed via flags
        if (null !== $releaseChannel) {
            $env['PLAYCAST_VERSION'] = $releaseChannel;
        }
        if (null !== $httpPort) {
            $env['PLAYCAST_HTTP_PORT'] = $httpPort;
        }
        if (null !== $httpsPort) {
            $env['PLAYCAST_HTTPS_PORT'] = $httpsPort;
        }

        // Migrate legacy config values.
        if (isset($playcastEnv['PREFER_RELEASE_BUILDS'])) {
            $env['PLAYCAST_VERSION'] = ('true' === $playcastEnv['PREFER_RELEASE_BUILDS'])
                ? 'stable'
                : 'latest';

            unset($playcastEnv['PREFER_RELEASE_BUILDS']);
        }

        unset($playcastEnv['ENABLE_ADVANCED_FEATURES']);

        // Randomize the MariaDB root password for new installs.
        if ($isNewInstall) {
            if ($devMode) {
                if (empty($playcastEnv['MYSQL_ROOT_PASSWORD'])) {
                    $playcastEnv['MYSQL_ROOT_PASSWORD'] = 'azur4c457_root';
                }
            } else {
                if (
                    empty($playcastEnv[Environment::DB_PASSWORD])
                    || 'azur4c457' === $playcastEnv[Environment::DB_PASSWORD]
                ) {
                    $playcastEnv[Environment::DB_PASSWORD] = Strings::generatePassword(12);
                }

                if (empty($playcastEnv['MYSQL_ROOT_PASSWORD'])) {
                    $playcastEnv['MYSQL_ROOT_PASSWORD'] = Strings::generatePassword(20);
                }
            }
        }

        if (!empty($playcastEnv['MYSQL_ROOT_PASSWORD'])) {
            unset($playcastEnv['MYSQL_RANDOM_ROOT_PASSWORD']);
        } else {
            $playcastEnv['MYSQL_RANDOM_ROOT_PASSWORD'] = 'yes';
        }

        // Display header messages
        if ($isNewInstall) {
            $io->title(
                __('PlayCast Installer')
            );
            $io->block(
                __('Welcome to PlayCast! Complete the initial server setup by answering a few questions.')
            );

            $customize = !$defaults;
        } else {
            $io->title(
                __('PlayCast Updater')
            );

            if ($defaults) {
                $customize = false;
            } else {
                $customize = $io->confirm(
                    __('Change installation settings?'),
                    false
                );
            }
        }

        if ($customize) {
            // Port customization
            $io->writeln(
                __('PlayCast is currently configured to listen on the following ports:'),
            );
            $io->listing(
                [
                    __('HTTP Port: %d', $env['PLAYCAST_HTTP_PORT']),
                    __('HTTPS Port: %d', $env['PLAYCAST_HTTPS_PORT']),
                    __('SFTP Port: %d', $env['PLAYCAST_SFTP_PORT']),
                    __('Radio Ports: %s', $env['PLAYCAST_STATION_PORTS']),
                ],
            );

            $customizePorts = $io->confirm(
                __('Customize ports used for PlayCast?'),
                false
            );

            if ($customizePorts) {
                $simplePorts = [
                    'PLAYCAST_HTTP_PORT',
                    'PLAYCAST_HTTPS_PORT',
                    'PLAYCAST_SFTP_PORT',
                ];

                foreach ($simplePorts as $port) {
                    $env[$port] = (int)$io->ask(
                        $envConfig[$port]['name'] . ' - ' . $envConfig[$port]['description'],
                        (string)$env[$port]
                    );
                }

                $playcastEnv[Environment::AUTO_ASSIGN_PORT_MIN] = (int)$io->ask(
                    $playcastEnvConfig[Environment::AUTO_ASSIGN_PORT_MIN]['name'],
                    (string)$playcastEnv[Environment::AUTO_ASSIGN_PORT_MIN]
                );

                $playcastEnv[Environment::AUTO_ASSIGN_PORT_MAX] = (int)$io->ask(
                    $playcastEnvConfig[Environment::AUTO_ASSIGN_PORT_MAX]['name'],
                    (string)$playcastEnv[Environment::AUTO_ASSIGN_PORT_MAX]
                );

                $stationPorts = Configuration::enumerateDefaultPorts(
                    rangeMin: $playcastEnv[Environment::AUTO_ASSIGN_PORT_MIN],
                    rangeMax: $playcastEnv[Environment::AUTO_ASSIGN_PORT_MAX]
                );
                $env['PLAYCAST_STATION_PORTS'] = implode(',', $stationPorts);
            }

            $customizeLetsEncrypt = $io->confirm(
                __('Set up LetsEncrypt?'),
                false
            );

            if ($customizeLetsEncrypt) {
                $env['LETSENCRYPT_HOST'] = $io->ask(
                    $envConfig['LETSENCRYPT_HOST']['description'],
                    $env['LETSENCRYPT_HOST']
                );

                $env['LETSENCRYPT_EMAIL'] = $io->ask(
                    $envConfig['LETSENCRYPT_EMAIL']['description'],
                    $env['LETSENCRYPT_EMAIL']
                );
            }
        }

        $io->writeln(
            __('Writing configuration files...')
        );

        $envStr = $env->writeToFile();
        $playcastEnvStr = $playcastEnv->writeToFile();

        if ($io->isVerbose()) {
            $io->section($env->getBasename());
            $io->block($envStr);

            $io->section($playcastEnv->getBasename());
            $io->block($playcastEnvStr);
        }

        $dockerComposePath = ($devMode)
            ? $baseDir . '/docker-compose.yml'
            : $baseDir . '/docker-compose.new.yml';
        $dockerComposeStr = $this->updateDockerCompose($dockerComposePath, $env, $playcastEnv);

        if ($io->isVerbose()) {
            $io->section(basename($dockerComposePath));
            $io->block($dockerComposeStr);
        }

        $io->success(
            __('Server configuration complete!')
        );
        return 0;
    }

    protected function updateDockerCompose(
        string $dockerComposePath,
        AbstractEnvFile $env,
        AbstractEnvFile $playcastEnv
    ): string {
        // Attempt to parse Docker Compose YAML file
        $sampleFile = $this->environment->getBaseDirectory() . '/docker-compose.sample.yml';
        $yaml = Yaml::parseFile($sampleFile);

        // Parse port listing and convert into YAML format.
        $ports = $env['PLAYCAST_STATION_PORTS'] ?? '';

        $envConfig = $env::getConfiguration();
        $defaultPorts = $envConfig['PLAYCAST_STATION_PORTS']['default'];

        if (!empty($ports) && 0 !== strcmp($ports, $defaultPorts)) {
            $yamlPorts = [];
            $nginxRadioPorts = [];
            $nginxWebDjPorts = [];

            foreach (explode(',', $ports) as $port) {
                $port = (int)$port;
                if ($port <= 0) {
                    continue;
                }

                $yamlPorts[] = $port . ':' . $port;

                if (0 === $port % 10) {
                    $nginxRadioPorts[] = $port;
                } elseif (5 === $port % 10) {
                    $nginxWebDjPorts[] = $port;
                }
            }

            if (!empty($yamlPorts)) {
                $yaml['services']['stations']['ports'] = $yamlPorts;
            }
            if (!empty($nginxRadioPorts)) {
                $nginxRadioPortsStr = '(' . implode('|', $nginxRadioPorts) . ')';
                $yaml['services']['web']['environment']['NGINX_RADIO_PORTS'] = $nginxRadioPortsStr;
            }
            if (!empty($nginxWebDjPorts)) {
                $nginxWebDjPortsStr = '(' . implode('|', $nginxWebDjPorts) . ')';
                $yaml['services']['web']['environment']['NGINX_WEBDJ_PORTS'] = $nginxWebDjPortsStr;
            }
        }

        // Remove Redis if it's not enabled.
        $enableRedis = $playcastEnv->getAsBool(Environment::ENABLE_REDIS, true);
        if (!$enableRedis) {
            unset($yaml['services']['redis']);
        }

        // Remove LetsEncrypt if it's not enabled.
        $letsEncryptHost = $env['LETSENCRYPT_HOST'] ?? null;
        $letsEncryptEmail = $env['LETSENCRYPT_EMAIL'] ?? null;

        if (empty($letsEncryptHost)) {
            unset(
                $yaml['services']['nginx_proxy_letsencrypt'],
                $yaml['services']['web']['environment']['LETSENCRYPT_HOST'],
                $yaml['services']['web']['environment']['LETSENCRYPT_EMAIL']
            );
        } elseif (empty($letsEncryptEmail)) {
            unset(
                $yaml['services']['web']['environment']['LETSENCRYPT_EMAIL'],
                $yaml['services']['nginx_proxy_letsencrypt']['environment']['DEFAULT_EMAIL']
            );
        }

        // Remove privileged-mode settings if not enabled.
        $enablePrivileged = $env->getAsBool('PLAYCAST_COMPOSE_PRIVILEGED', true);
        if (!$enablePrivileged) {
            foreach ($yaml['services'] as &$service) {
                unset(
                    $service['ulimits'],
                    $service['sysctls']
                );
            }
            unset($service);
        }

        $yamlRaw = Yaml::dump($yaml, PHP_INT_MAX);
        file_put_contents($dockerComposePath, $yamlRaw);

        return $yamlRaw;
    }
}
