<?php

namespace Sam\PandocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class PandocConvertCommand extends ContainerAwareCommand {

    const FORMAT_MD2MW = "md2mw";

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var Question
     */
    private $question;

    protected function configure() {

        $this
            ->setName('pandoc:convert')
            ->setDescription('Convert Wiki Git to MediaWiki')
            ->addOption('input', 'i', InputOption::VALUE_OPTIONAL, 'fichier à convertir')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'fichier converti')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'format de conversion', SELF::FORMAT_MD2MW)
            ->addOption('wiki', 'w', InputOption::VALUE_REQUIRED, 'path/to/wiki/directory');;
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
        $this->question = $this->getHelper('question');
        $proc = new Process('pandoc');
        $fs = new Filesystem();
        $finder = new Finder();

        $fileIn = $input->getOption('input');
        $fileOut = $input->getOption('output');
        $format = $input->getOption('format');
        $wikiDir = $input->getOption('wiki');

        if (!$fs->exists($wikiDir)) {
            $output->writeln("wiki directory not exist at $wikiDir");
        }


        //check dir markdown
        if (!$fs->exists("$wikiDir/markdown")) {
            $this->warn("markdown directory not exist at $wikiDir");
            $q = new ConfirmationQuestion("Do you want to create markdown directory?", false, "/^(y|Y|o|O)/i");
            if ($this->question->ask($input, $output, $q)) {
                $fs->mkdir("$wikiDir/markdown");
                $this->info("markdown directory created");
            }
        }
        //check if mediawiki dir exist
        if (!$fs->exists("$wikiDir/mediawiki")) {
            $this->warn("mediawiki directory not exist at $wikiDir");
            $q = new ConfirmationQuestion("Do you want to create markdown directory?", false, "/^(y|Y|o|O)/i");
            if ($this->question->ask($input, $output, $q)) {
                $fs->mkdir("$wikiDir/mediawiki");
                $this->info("mediawiki directory created");
            }
        }




        if ($fileIn !== null) {
            $file = $finder->in($wikiDir)->files()->name($fileIn);
            dump($file);die('ok');
            $this->convert($file, $fileOut, $format);
        } else {
            $files = [];
            foreach ($finder->in($wikiDir)->files()->sortByName() as $file) {
                $files[] = $file->getFileName();
            }
            $filesList = $files;
            array_unshift($filesList, "all");
            $question = new ChoiceQuestion(
                'Choose file(s) to convert:',
                $filesList,
                'all'
            );
            $question->setMultiselect(true);

            $files2Convert = $this->question->ask($input, $output, $question);
            if(count($files2Convert) === 1 && $files2Convert[0] === "all") {
                $files2Convert = $files;

            }
//            $this->convert($wikiDir, $files2Convert, )
        }


        $output->writeln('end.');
    }

    /**
     * @param $file
     * @param $fileOut
     * @param $format
     */
    private function convert($file, $fileOut, $format) {

    }

    private function error($msg) {
        $this->output->writeln($msg);
    }

    private function warn($msg) {
        $this->output->writeln($msg);
    }

    private function info($msg) {
        $this->output->writeln($msg);
    }

}
