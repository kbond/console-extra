<?php

namespace Zenstruck\RadCommand;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlockFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use function Symfony\Component\String\u;

/**
 * @internal
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class CommandDocblock
{
    /** @var array<class-string, DocBlock> */
    private static array $docblocks = [];
    private static ?DocBlockFactory $factory;

    private string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function name(): ?string
    {
        if (!$this->docblock()->hasTag('command')) {
            return null;
        }

        return $this->docblock()->getTagsByName('command')[0];
    }

    public function description(): ?string
    {
        return u($this->docblock()->getSummary())->replace("\n", ' ')->toString() ?: null;
    }

    public function help(): ?string
    {
        return $this->docblock()->getDescription() ?: null;
    }

    /**
     * @return iterable<array>
     */
    public function arguments(): iterable
    {
        foreach ($this->docblock()->getTagsByName('argument') as $tag) {
            yield $this->parseArgumentTag($tag);
        }
    }

    /**
     * @return iterable<array>
     */
    public function options(): iterable
    {
        foreach ($this->docblock()->getTagsByName('option') as $tag) {
            yield $this->parseOptionTag($tag);
        }
    }

    private function parseArgumentTag(Tag $tag): array
    {
        if (\preg_match('#^(\?)?([\w\-]+)(=([\w\-]+))?(\s+(.+))?$#', $tag, $matches)) {
            $default = $matches[4] ?? null;

            return [
                $matches[2], // name
                $matches[1] || $default ? InputArgument::OPTIONAL : InputArgument::REQUIRED, // mode
                $matches[6] ?? '', // description
                $default ?: null, // default
            ];
        }

        // try matching with quoted default
        if (\preg_match('#^([\w\-]+)=["\'](.+)["\'](\s+(.+))?$#', $tag, $matches)) {
            return [
                $matches[1], // name
                InputArgument::OPTIONAL, // mode
                $matches[4] ?? '', // description
                $matches[2], // default
            ];
        }

        // try matching array argument
        if (\preg_match('#^(\?)?([\w\-]+)\[\](\s+(.+))?$#', $tag, $matches)) {
            return [
                $matches[2], // name
                InputArgument::IS_ARRAY | ($matches[1] ? InputArgument::OPTIONAL : InputArgument::REQUIRED), // mode
                $matches[4] ?? '', // description
            ];
        }

        throw new \LogicException(\sprintf('Argument tag "%s" on "%s" is malformed.', $tag->render(), $this->class));
    }

    private function parseOptionTag(Tag $tag): array
    {
        if (\preg_match('#^(([\w\-]+)\|)?([\w\-]+)(=([\w\-]+)?)?(\s+(.+))?$#', $tag, $matches)) {
            $default = $matches[5] ?? null;

            return [
                $matches[3], // name
                $matches[2] ?: null, // shortcut
                $matches[4] ?? null ? InputOption::VALUE_REQUIRED : InputOption::VALUE_NONE, // mode
                $matches[7] ?? '', // description
                $default ?: null, // default
            ];
        }

        // try matching with quoted default
        if (\preg_match('#^(([\w\-]+)\|)?([\w\-]+)=["\'](.+)["\'](\s+(.+))?$#', $tag, $matches)) {
            return [
                $matches[3], // name
                $matches[2] ?: null, // shortcut
                InputOption::VALUE_REQUIRED, // mode
                $matches[6] ?? '', // description
                $matches[4], // default
            ];
        }

        // try matching array option
        if (\preg_match('#^(([\w\-]+)\|)?([\w\-]+)\[\](\s+(.+))?$#', $tag, $matches)) {
            return [
                $matches[3], // name
                $matches[2] ?: null, // shortcut
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, // mode
                $matches[5] ?? '', // description
            ];
        }

        throw new \LogicException(\sprintf('Option tag "%s" on "%s" is malformed.', $tag->render(), $this->class));
    }

    private static function factory(): DocBlockFactory
    {
        return self::$factory ??= DocBlockFactory::createInstance();
    }

    private function docblock(): DocBlock
    {
        return self::$docblocks[$this->class] ??= self::factory()
            ->create((new \ReflectionClass($this->class))->getDocComment())
        ;
    }
}
