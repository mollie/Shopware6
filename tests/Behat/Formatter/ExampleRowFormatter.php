<?php

declare(strict_types=1);

namespace Mollie\Shopware\Behat\Formatter;

use Behat\Behat\EventDispatcher\Event\AfterOutlineTested;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Testwork\EventDispatcher\Event\ExerciseCompleted;
use Vanare\BehatCucumberJsonFormatter\Formatter\Formatter;
use Vanare\BehatCucumberJsonFormatter\Node\Feature;

/**
 * Two things the Cucumber formatter gets wrong about an Examples table, and the Allure
 * report shows both:
 *
 * 1. Every row is reported under the title of the Scenario Outline, so all rows of one
 *    outline carry the same name. Allure builds a test's identity from that name, so the
 *    rows collapsed into retries of a single test - the report counted 34 Behat tests where
 *    64 scenarios had run. Every row now gets the values it ran with appended to its name.
 * 2. The formatter splits the recorded steps per row by the number of steps in the outline,
 *    but the steps of a Background are recorded as well. With a Background every row
 *    therefore gets a window shifted by the rows before it, and the last rows run off the
 *    end of the list. It is handed an outline that carries the Background steps, which is
 *    the number its split actually needs.
 *
 * A Behat formatter is a Behat service, and it is not this plugin's code that instantiates
 * it - so this cannot live on an existing class. Registered by ExampleRowExtension.
 */
final class ExampleRowFormatter extends Formatter
{
    /**
     * The values of every Examples row that ran, by feature file and the line the row sits
     * on. Both, because line numbers repeat across feature files.
     *
     * @var array<string, string>
     */
    private array $valuesByRow = [];

    /**
     * Behat dispatches one event per outline, not per row, and the events return nothing -
     * so this breaks the no-void rule of the PHP guidelines. The signature is Behat's.
     */
    public function onAfterOutlineTested(AfterOutlineTested $event): void
    {
        // The deprecated merged table on purpose: it is the table the formatter itself
        // reads the row line from, so the lines recorded here are the ones it writes out.
        $table = $event->getOutline()->getExampleTable();
        $rows = $table->getRows();
        $rowCount = count($rows);
        $file = $event->getFeature()->getFile();

        // Row 0 holds the column names.
        for ($index = 1; $index < $rowCount; ++$index) {
            $this->valuesByRow[$this->rowKey($file, $table->getRowLine($index))] = implode(', ', $rows[$index]);
        }

        parent::onAfterOutlineTested($this->withBackgroundSteps($event));
    }

    public function onAfterExercise(ExerciseCompleted $event): void
    {
        // Every feature has been collected by now, and the renderer has not run yet - it is
        // the parent call below that starts it.
        foreach ($this->getSuites() as $suite) {
            $features = $suite->getFeatures();

            foreach ($features as $feature) {
                $this->nameExampleRows($feature);
            }
        }

        parent::onAfterExercise($event);
    }

    private function nameExampleRows(Feature $feature): void
    {
        $scenarios = $feature->getScenarios();

        foreach ($scenarios as $scenario) {
            $values = $this->valuesByRow[$this->rowKey($feature->getFile(), $scenario->getLine())] ?? '';

            // A plain scenario sits on its own line, never on the line of an Examples row.
            if (strlen($values) === 0) {
                continue;
            }

            $scenario->setName(sprintf('%s [%s]', $scenario->getName(), $values));
        }
    }

    private function withBackgroundSteps(AfterOutlineTested $event): AfterOutlineTested
    {
        $background = $event->getFeature()->getBackground();

        if ($background === null) {
            return $event;
        }

        $outline = $event->getOutline();
        $steps = array_merge($background->getSteps(), $outline->getSteps());

        return new AfterOutlineTested(
            $event->getEnvironment(),
            $event->getFeature(),
            new OutlineNode(
                $outline->getTitle(),
                $outline->getTags(),
                $steps,
                $outline->getExampleTables(),
                $outline->getKeyword(),
                $outline->getLine()
            ),
            $event->getTestResult(),
            $event->getTeardown()
        );
    }

    private function rowKey(string $file, int $line): string
    {
        return $file . ':' . $line;
    }
}
