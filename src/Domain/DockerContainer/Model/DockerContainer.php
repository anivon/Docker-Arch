<?php

namespace Ph3\DockerArch\Domain\DockerContainer\Model;

use Ph3\DockerArch\Domain\Service\Model\ServiceInterface;
use Ph3\DockerArch\Domain\TemplatedFile\Model\TemplatedFile;

/**
 * @author Cédric Dugat <cedric@dugat.me>
 */
class DockerContainer implements DockerContainerInterface
{
    const DOCKER_MAIN_NETWORK = 'main-network';

    use DockerContainerBasicPropertiesTrait;
    use DockerContainerPackagesTrait;
    use DockerContainerCommandsTrait;
    use DockerContainerEnvsTrait;
    use DockerContainerPortsTrait;
    use DockerContainerVolumesTrait;
    use DockerContainerNetworksTrait;
    use DockerContainerCopyEntriesTrait;

    /**
     * @var string
     */
    private $packageManager = 'apt';

    /**
     * `false` for instant.
     * Depends on each instances/images User management.
     *
     * @var bool
     */
    private $userCreation = false;

    /**
     * @var ServiceInterface
     */
    private $service;

    /**
     * @param ServiceInterface $service
     */
    public function __construct(ServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this
            ->setMaintainer('Docker Arch <https://github.com/Ph3nol/Docker-Arch>')
            ->addEnv('DOCKER_CONTAINER_NAME', $this->getService()->getIdentifier())
            ->addEnv('DEBIAN_FRONTEND', 'noninteractive')
            ->addNetwork(self::DOCKER_MAIN_NETWORK);

        $this->addPackage('vim');

        if (true === $this->getService()->isWebService()) {
            $this
                ->addEnv('TERM', 'xterm-256color')
                ->addEnv('GIT_DISCOVERY_ACROSS_FILESYSTEM', 'true')
                ->addPackage('git')
                ->addPackage('less')
                ->addPackage('wget')
                ->addPackage('curl')
                ->addPackage('openssh-client');
        }

        if (true === $this->isPackageManager(self::PACKAGE_MANAGER_TYPE_APK)) {
            $this
                ->addPackage('bash')
                ->addPackage('findutils')
                ->addPackage('ca-certificates')
                ->addPackage('openssl')
                ->addCommand('update-ca-certificates');
        }

        $this->initLocale();
        $this->initUser();
    }

    /**
     * @return string
     */
    public function getUserHomeDirectory(): string
    {
        return ('root' === $this->getUser()) ? '/root' : '/home/'.$this->getUser();
    }

    /**
     * @return self
     */
    public function disableUserCreation(): self
    {
        $this->userCreation = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isUserCreationEnabled(): bool
    {
        return $this->userCreation;
    }

    /**
     * @return string
     */
    public function getPackageManager(): string
    {
        return $this->packageManager;
    }

    /**
     * @param string $packageManager
     *
     * @return bool
     */
    public function isPackageManager($packageManager): bool
    {
        return ($packageManager === $this->getPackageManager());
    }

    /**
     * @param string $packageManager
     *
     * @return self
     */
    public function setPackageManager(string $packageManager): self
    {
        $this->packageManager = $packageManager;

        return $this;
    }

    /**
     * @param string $key
     *
     * @return self
     */
    public function addEnvFromProject(string $key): self
    {
        $this->envs[$key] = '${'.$this->getService()->getProject()->generateEnvKey($key).'}';

        return $this;
    }

    /**
     * @param string $envProperty
     * @param array  $port
     *
     * @return array
     */
    public function addEnvPort(string $envProperty, array $port): array
    {
        $service = $this->getService();
        $project = $service->getProject();

        $portKey = $service->generateEnvKey($envProperty.'_PORT');
        $project->addEnv($portKey, $port['from']);
        $port['from'] = '${'.$project->generateEnvKey($portKey).'}';
        $this->addPort($port);

        return $port;
    }

    /**
     * @return ServiceInterface
     */
    public function getService(): ServiceInterface
    {
        return $this->service;
    }

    /**
     * @param string $relativePath
     *
     * @return string
     */
    protected function getAbsoluteUserPath(string $relativePath): string
    {
        return str_replace('~/', $this->getUserHomeDirectory().'/', $relativePath);
    }

    /**
     * @return void
     */
    protected function applyDotfiles(): void
    {
        // Volumes.
        $this
            ->addVolume([
                'local' => '~/.ssh', 'remote' => $this->getAbsoluteUserPath('~/.ssh'), 'type' => 'ro'
            ])
            ->addVolume([
                'local' => '~/.gitconfig', 'remote' => $this->getAbsoluteUserPath('~/.gitconfig'), 'type' => 'ro'
            ])
            ->addVolume([
                'local' => '~/.gitignore', 'remote' => $this->getAbsoluteUserPath('~/.gitignore'), 'type' => 'ro'
            ]);
    }

    /**
     * @return void
     */
    protected function applyShellConfiguration(): void
    {
        if (true === $this->getService()->getOptions()['zsh']) {
            $this
                ->addPackage('zsh')
                ->addCommand('echo "source ~/.shell.config" >> ~/.zshrc');
        } else {
            $this->addCommand('echo "source ~/.shell.config" >> ~/.bashrc');
        }
        if (true === $this->getService()->getOptions()['zsh'] &&
            true === $this->getService()->getOptions()['custom_zsh']) {
            $this
                ->addCommand('curl https://cdn.rawgit.com/zsh-users/antigen/v1.4.1/bin/antigen.zsh > ~/.antigen.zsh');
        }
        $this->getService()->addTemplatedFile(new TemplatedFile(
            'dotfiles/.shell.config',
            'Service/Common/shell.config.twig'
        ));
        $this->addCopyEntry([
            'local' => 'dotfiles/.shell.config',
            'remote'=> $this->getAbsoluteUserPath('~/.shell.config'),
        ]);
    }

    /**
     * @return void
     */
    protected function applyWebServiceConfiguration(): void
    {
        if (null === $this->getWorkingDir()) {
            $this->setWorkingDir(sprintf(
                '/apps/%s',
                $this->getService()->getHost() ? : $this->getService()->getIdentifier()
            ));
        }
    }

    /**
     * @return void
     */
    private function initLocale(): void
    {
        $locale = $this->getService()->getProject()->getLocale();

        if (true === $this->isPackageManager(self::PACKAGE_MANAGER_TYPE_APT)) {
            $this
                // Packages.
                ->addPackage('locales')
                ->addPackage('zlib1g-dev')
                ->addPackage('libicu-dev')
                ->addPackage('g++')
                // Commands.
                ->addCommand(sprintf('echo "%s.UTF-8 UTF-8" > /etc/locale.gen', $locale))
                ->addCommand(sprintf('locale-gen %s.UTF-8', $locale))
                ->addCommand('dpkg-reconfigure locales')
                ->addCommand(sprintf('/usr/sbin/update-locale LANG=%s.UTF-8', $locale))
                // Envs.
                ->addEnv('LC_ALL', sprintf('%s.UTF-8', $locale));
        }
    }

    /**
     * @return void
     */
    private function initUser(): void
    {
        /**
         * Set as `root` for instant.
         * Will be generated from self::initUser() lately.
         */
        $this->setUser('root');

        return ;

        // User actions.
        $user = $this->getService()->getProject()->getUser();
        if (null === $user) {
            $user = 'docker-arch';
        }

        $this->setUser($user);

        if (false === $this->isUserCreationEnabled()) {
            return ;
        }

        if ('root' === $user) {
            return ;
        }

        if (false === $this->isPackageManager(self::PACKAGE_MANAGER_TYPE_APK)) {
            $this
                ->addPackage('adduser')
                ->addCommand(sprintf('groupadd %s || true', $user))
                ->addCommand(sprintf('useradd --create-home -g %s %s || true', $user, $user));
        } else {
            $this
                ->addCommand(sprintf('addgroup -S %s || true', $user))
                ->addCommand(sprintf('adduser -D -H -S -G %s %s || true', $user, $user));
        }
    }
}
