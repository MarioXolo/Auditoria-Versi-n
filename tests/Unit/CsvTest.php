<?php

namespace Tests\Unit;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

class CsvTest extends TestCase
{
    public function test_formula_cells_are_neutralized(): void
    {
        foreach (['=HYPERLINK("http://x","clic")', '@SUM(A1:A9)', '+cmd|calc', "-2+3+cmd|' /C calc'!A0", "\t=1+1"] as $evil) {
            $this->assertSame("'".$evil, Csv::cell($evil));
        }
    }

    public function test_normal_values_are_untouched(): void
    {
        foreach (['Juan Pérez', '+52 442 123 4567', '-150', '442-000-0000', '(442) 123 45 67', '', 'Sí'] as $ok) {
            $this->assertSame($ok, Csv::cell($ok));
        }
        $this->assertSame(12, Csv::cell(12));
        $this->assertNull(Csv::cell(null));
    }
}
