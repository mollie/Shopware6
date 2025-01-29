<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\TranslationImporter;

use League\Flysystem\Filesystem;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


final class ImportCSVCommand extends Command
{
    private Filesystem $fileSystem;
    private AppenderInterface $appender;
    private Plugin $plugin;


    public function __construct(Filesystem $fileSystem, AppenderInterface $appender, Plugin $plugin)
    {
        parent::__construct('mollie:translation:import');
        $this->fileSystem = $fileSystem;
        $this->appender = $appender;
        $this->plugin = $plugin;
    }

    protected function configure()
    {
       $this->setDescription('Import translations from CSV file and stores it into config.xml');
       $this->addArgument('path',InputArgument::REQUIRED,'Path to source CSV file');
       $this->addArgument('code',InputArgument::REQUIRED,'Language to import e.g. "it-IT"');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $localeCode = $input->getArgument('code');

        if(!$this->fileSystem->fileExists($path)){
            $output->writeln('<error>File not found: '.$path.'</error>');
            return Command::FAILURE;
        }



        $pathToConfigXml = realpath($this->plugin->getPath().'/Resources/config/config.xml');
        if($pathToConfigXml === false){
            $output->writeln('<error>Config file not found: '.$path.'</error>');
            return Command::FAILURE;
        }

        $stream = $this->fileSystem->readStream($path);
        $domDocument = new \DOMDocument();
        $domDocument->loadXML(file_get_contents($pathToConfigXml));
        $row = fgetcsv($stream,null,';');//skip header
        while($row = fgetcsv($stream,null,';')){
            $key = $row[0];
            $text = $row[2];
            $domDocument = $this->appender->append($domDocument,$key,$text,$localeCode);
        }
        $domDocument->save($pathToConfigXml);




        return Command::SUCCESS;
    }


}