<?php

declare(strict_types = 1);

namespace EugeneErg\OpenApi\Components\Schemas\Abstract;

use EugeneErg\OpenApi\Process;

/**
 * Границы числовой схемы. Вынесено отдельно, потому что 3.0 и 3.1 записывают
 * исключающие границы по-разному, а правило одинаково для integer и number.
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

                if ($this->exclusiveMinimum) {
                    $result['exclusiveMinimum'] = true;
                }
            }
        }

        if ($this->maximum !== null) {
            if ($isV31 && $this->exclusiveMaximum) {
                $result['exclusiveMaximum'] = $this->maximum;
            } else {
                $result['maximum'] = $this->maximum;

                if ($this->exclusiveMaximum) {
                    $result['exclusiveMaximum'] = true;
                }
            }
        }

        if ($this->multipleOf !== null) {
            $result['multipleOf'] = $this->multipleOf;
        }

        return $result;
    }
}
