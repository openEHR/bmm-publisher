<?php

declare(strict_types=1);

namespace OpenEHR\BmmPublisher\Writer\Formatter;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The site's `class_*` attributes (`resources/global-vars.yml` in antora-openehr), which the
 * Antora tables use as xref targets. Reports each attribute the tables link through that the
 * site cannot resolve to the class's anchor.
 */
final class AntoraClassMap
{
    /** @var array<string, true> */
    private array $checked = [];

    /**
     * @param array<string, string> $attributes `class_*` name => xref target, e.g. `{page_rm_ehr}#_ehr_class`
     */
    public function __construct(
        private readonly array $attributes,
        private readonly string $source,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function fromFile(string $path, LoggerInterface $logger): self
    {
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new RuntimeException(\sprintf('Class map %s is not valid YAML: %s', $path, $e->getMessage()), 0, $e);
        }
        if (!\is_array($data)) {
            throw new RuntimeException(\sprintf('Class map %s is not a YAML mapping.', $path));
        }
        $attributes = [];
        foreach ($data as $name => $value) {
            if (\is_string($name) && str_starts_with($name, 'class_') && \is_string($value)) {
                $attributes[$name] = $value;
            }
        }
        // A list, or another YAML file passed by mistake, would report every class link as missing.
        if ($attributes === []) {
            throw new RuntimeException(\sprintf('Class map %s defines no class_* attributes.', $path));
        }
        return new self($attributes, $path, $logger);
    }

    /**
     * Warn, once per attribute, when the map has no entry for it or its target is not the anchor.
     */
    public function check(string $attribute, string $anchor, string $schemaId): void
    {
        if (isset($this->checked[$attribute])) {
            return;
        }
        $this->checked[$attribute] = true;
        $context = ['attribute' => $attribute, 'schema' => $schemaId, 'map' => $this->source, 'anchor' => $anchor];
        $target = $this->resolve($attribute);
        if ($target === null) {
            $this->logger->warning('{attribute} (used in {schema}) has no entry in {map}, so its links do not resolve.', $context);
        } elseif (!str_ends_with($target, '#' . $anchor)) {
            $this->logger->warning(
                '{attribute} (used in {schema}) points at {target} in {map}; the class anchor is #{anchor}.',
                $context + ['target' => $target],
            );
        }
    }

    /**
     * The target of an attribute, following entries that are just another class attribute
     * (`class_constraint_status: "{class_aom2_constraint_status}"`).
     */
    private function resolve(string $attribute): ?string
    {
        $seen = [];
        while (isset($this->attributes[$attribute]) && !isset($seen[$attribute])) {
            $seen[$attribute] = true;
            if (preg_match('/^\{(class_\w+)\}$/', $this->attributes[$attribute], $m) !== 1) {
                return $this->attributes[$attribute];
            }
            $attribute = $m[1];
        }
        return null;
    }
}
