<?php

namespace Sam\PandocBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
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

    private $additionnalReplace = [
        self::FORMAT_MD2MW => [
            "#<a name=.*a>#" => "",//balise href a name  à supprimer
            "#&gt;#"=> ">" //chevron dans source
        ]
    ];

    protected function configure() {

        $this
            ->setName('pandoc:convert')
            ->setDescription('Convert Wiki Git to MediaWiki')
            ->addOption('wiki', 'w', InputOption::VALUE_REQUIRED, 'path/to/wiki/directory', false)
            ->addOption('input', 'i', InputOption::VALUE_OPTIONAL, 'fichier à convertir')
//            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'fichier converti')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'format de conversion', SELF::FORMAT_MD2MW);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        try {
            $this->input = $input;
            $this->output = $output;
            $this->question = $this->getHelper('question');

            $fs = new Filesystem();
            $finder = new Finder();

            $fileIn = $input->getOption('input');
//            $fileOut = $input->getOption('output');
            $format = $input->getOption('format');
            $wikiDir = $input->getOption('wiki');
            if ($wikiDir === false) {
                throw new \Exception ("wiki directory not specified");
            }

            if (!$fs->exists($wikiDir)) {
                throw new \Exception("wiki directory not exist at $wikiDir");
            }


            //check dir markdown
            if (!$fs->exists("$wikiDir/markdown")) {
                $this->warn("markdown directory not exist at $wikiDir");
                $q = new ConfirmationQuestion("Do you want to create markdown directory?", false, "/^(y|Y|o|O)/i");
                if ($this->question->ask($input, $output, $q)) {
                    $fs->mkdir("$wikiDir/markdown");
                    $this->info("markdown directory created");
                } else {
                    throw new \Exception("markdown directory needed");
                }
            }
            //check if mediawiki dir exist
            if (!$fs->exists("$wikiDir/mediawiki")) {
                $this->warn("mediawiki directory not exist at $wikiDir");
                $q = new ConfirmationQuestion("Do you want to create markdown directory?", false, "/^(y|Y|o|O)/i");
                if ($this->question->ask($input, $output, $q)) {
                    $fs->mkdir("$wikiDir/mediawiki");
                    $this->info("mediawiki directory created");
                } else {
                    throw new \Exception("mediawiki directory needed");
                }
            }


            if ($fileIn !== null) {
                $files = $finder->in($wikiDir)->files()->name("#$fileIn#")->depth(0);
                if ($files->count() === 1) {
                    $filesArray = iterator_to_array($files->getIterator());
                    $file = array_shift($filesArray);
                    $fileName = $file->getFileName();
                    $q = new ConfirmationQuestion("confirm convert file $fileName?", false, "/^(y|Y|o|O)/i");
                    if ($this->question->ask($input, $output, $q)) {
                        $this->convert($wikiDir, [$fileName], $format);
                    } else {
                        throw new \Exception("mediawiki directory needed");
                    }

                } else {
                    throw new \Exception("file $fileIn not found at $wikiDir");
                }
            } else {
                $files = [];
                foreach ($finder->in($wikiDir)->files()->sortByName()->depth(0)->name("#\.md$#") as $file) {
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
                if (count($files2Convert) === 1 && $files2Convert[0] === "all") {
                    $files2Convert = $files;

                }
                $this->convert($wikiDir, $files2Convert, $format);
            }

        } catch (\Exception $e) {
            return $this->error($e->getMessage() . " at " . $e->getLine());
        }

        $output->writeln('end.');
    }

    /**
     * Convert with Pandoc
     *
     * @param string $wikiDir
     * @param array  $files to convert
     * @param string $format
     */
    private function convert($wikiDir, $files, $format) {
//        dump($wikiDir);
//        dump($files);
//        dump($format);

        switch ($format) {
            case self::FORMAT_MD2MW:
                foreach ($files as $file) {
                    $fileIn = "$wikiDir/markdown/$file";
                    //copie de l'original vers le répertoire markdown
                    $fs = new Filesystem();
                    $fs->copy("$wikiDir/$file", $fileIn);
                    $fileOut = "$wikiDir/mediawiki/" . preg_replace("#\.md$#", ".mw", $file);
                    $this->md2mw($fileIn, $fileOut);
                }

                break;
            default:
                throw new \Exception("format $format not expected");
                break;
        }

    }

    private function md2mw($fileIn, $fileOut) {
//dump($fileIn);
//dump($fileOut);
        $cmd = "pandoc $fileIn -f markdown-yaml_metadata_block -t mediawiki -o $fileOut";
        $process = new Process($cmd);
        $process->run();
        // executes after the command finishes
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        //modification additionnal
        //etag a href #
        $fs = new Filesystem();
        $content = file_get_contents($fileOut);
        $content = preg_replace(array_keys($this->additionnalReplace[self::FORMAT_MD2MW]), array_values($this->additionnalReplace[self::FORMAT_MD2MW]), $content);
        file_put_contents($fileOut, $content);


        return true;
//    #delete tag  <a>
//    sed -i 's/<a.*\/a>//g' "$mw"
    }


    private function error($msg) {
        $this->output->writeln("<error>$msg</error>");
    }

    private function warn($msg) {
        $this->output->writeln("<warning>$msg</warning>");
    }

    private function info($msg) {
        $this->output->writeln("<info>$msg</info>");
    }

}
