<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;

/**
 * The bounds of a numeric schema. Kept apart because 3.0 and 3.1 spell the exclusive
 * bounds differently, while the rule is the same for integer and for number.
 */
trait NumericRange
{
    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function appendRange(array $result, Process $process): array
    {
        $isV31 = $process->version()->isV31();

        if ($this->minimum !== null) {
            if ($isV31 && $this->exclusiveMinimum) {
                $result['exclusiveMinimum'] = $this->minimum;
            } else {
                $result['minimum'] = $this->minimum;

                // By 3.0, "if exclusiveMinimum is present, minimum MUST be present", so
                // the verbose form spells the flag out only beside a bound. In 3.1 the
                // keyword is the bound itself, and there is nothing to spell out.

                if ($this->exclusiveMinimum || ($process->verbose && !$isV31)) {
                    $result['exclusiveMinimum'] = $this->exclusiveMinimum;
                }
            }
        }

        if ($this->maximum !== null) {
            if ($isV31 && $this->exclusiveMaximum) {
                $result['exclusiveMaximum'] = $this->maximum;
            } else {
                $result['maximum'] = $this->maximum;

                if ($this->exclusiveMaximum || ($process->verbose && !$isV31)) {
                    $result['exclusiveMaximum'] = $this->exclusiveMaximum;
                }
            }
        }

        if ($this->multipleOf !== null) {
            $result['multipleOf'] = $this->multipleOf;
        }

        return $result;
    }
}
