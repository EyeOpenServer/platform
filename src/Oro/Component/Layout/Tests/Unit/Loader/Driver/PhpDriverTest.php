<?php

namespace Oro\Component\Layout\Tests\Unit\Loader\Driver;

use Symfony\Component\Filesystem\Filesystem;

use Oro\Component\Layout\Exception\SyntaxException;
use Oro\Component\Layout\Loader\Driver\PhpDriver;
use Oro\Component\Layout\Loader\Generator\GeneratorData;
use Oro\Component\Layout\Loader\Generator\LayoutUpdateGeneratorInterface;

class PhpDriverTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadInDebugMode()
    {
        $generator = $this->getMock('Oro\Component\Layout\Loader\Generator\LayoutUpdateGeneratorInterface');
        $loader    = $this->getLoader($generator);

        $generator->expects($this->once())->method('generate')->willReturnCallback([$this, 'buildClass']);

        $path = rtrim(__DIR__, DIRECTORY_SEPARATOR) . '/../Stubs/Updates/layout_update.php';
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $update = $loader->load($path);
        $this->assertInstanceOf('Oro\Component\Layout\LayoutUpdateInterface', $update);
    }

    public function testLoadInProductionMode()
    {
        $fs  = new Filesystem();
        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . time();

        $generator = $this->getMock('Oro\Component\Layout\Loader\Generator\LayoutUpdateGeneratorInterface');
        $loader    = $this->getLoader($generator, false, $dir);

        $generator->expects($this->once())->method('generate')->willReturnCallback([$this, 'buildClass']);

        $path = rtrim(__DIR__, DIRECTORY_SEPARATOR) . '/../Stubs/Updates/layout_update2.php';
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $update = $loader->load($path);
        $this->assertInstanceOf('Oro\Component\Layout\LayoutUpdateInterface', $update);
        $this->assertCount(1, $files = iterator_to_array(new \FilesystemIterator($dir)));

        $fs->remove($dir);
    }

    public function testProcessSyntaxExceptions()
    {
        $generator = $this->getMock('Oro\Component\Layout\Loader\Generator\LayoutUpdateGeneratorInterface');
        $loader    = $this->getLoader($generator);

        $exception = new SyntaxException(
            'Some error found',
            "\$layoutManipulator->add('header', 'root', 'header');\n",
            0
        );
        $generator->expects($this->once())->method('generate')->willThrowException($exception);

        $message = <<<MESSAGE
Syntax error: Some error found at "0"
\$layoutManipulator->add('header', 'root', 'header');


Filename:
MESSAGE;
        $this->setExpectedException('\RuntimeException', $message);

        $path     = rtrim(__DIR__, DIRECTORY_SEPARATOR) . '/../Stubs/Updates/layout_update4.php';
        $path     = str_replace('/', DIRECTORY_SEPARATOR, $path);

        $update = $loader->load($path);
        $this->assertInstanceOf('Oro\Component\Layout\LayoutUpdateInterface', $update);
    }

    /**
     * @param string        $className
     * @param GeneratorData $data
     *
     * @return string
     */
    public function buildClass($className, GeneratorData $data)
    {
        $data = str_replace(['<?php', '<?', '?>'], '', $data->getSource());

        return <<<CLASS
<?php
    use \Oro\Component\Layout\LayoutManipulatorInterface;
    use \Oro\Component\Layout\LayoutItemInterface;

    class $className implements \Oro\Component\Layout\LayoutUpdateInterface
    {
        public function updateLayout(LayoutManipulatorInterface \$layoutManipulator, LayoutItemInterface \$item)
        {
            $data
        }
    }
CLASS;
    }

    /**
     * @param null|LayoutUpdateGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject $generator
     * @param bool                                                                         $debug
     * @param bool                                                                         $cache
     *
     * @return PhpDriver
     */
    protected function getLoader($generator = null, $debug = true, $cache = false)
    {
        $generator = null === $generator
            ? $this->getMock('Oro\Component\Layout\Loader\Generator\LayoutUpdateGeneratorInterface')
            : $generator;

        return new PhpDriver($generator, $debug, $cache);
    }
}
