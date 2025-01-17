<?php

namespace App\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'osm:deleted-users',
    description: 'Download and cache OpenStreetMap deleted users',
)]
class DeletedUsersCommand extends Command
{
    final public const CACHE_KEY = 'users_deleted';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly Filesystem $filesystem,
        private readonly CacheItemPoolInterface $cache
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Force process (even if it has already been cached)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $usersDeleted = $this->cache->getItem(self::CACHE_KEY);
        $usersDeleted->expiresAfter(new \DateInterval('PT1H'));

        if ($usersDeleted->isHit() && true !== $input->getOption('force')) {
            $io->note('OpenStreetMap deleted users is alread cached.');
        } else {
            $response = $this->client->request('GET', 'https://planet.openstreetmap.org/users_deleted/users_deleted.txt');
            $content = $response->getContent();

            $path = $this->filesystem->tempnam(sys_get_temp_dir(), 'users_deleted_', '.txt');

            $this->filesystem->dumpFile($path, $content);

            $list = array_map(fn ($line) => (int) trim($line), file($path, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES));

            $usersDeleted->set($list);

            $this->cache->save($usersDeleted);

            $io->success('OpenStreetMap deleted users have been downloaded and cached.');
        }

        return Command::SUCCESS;
    }
}
