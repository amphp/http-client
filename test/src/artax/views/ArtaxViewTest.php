<?php

class ArtaxViewTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
    $view = new ArtaxViewTestImplementation;
    $this->assertEmpty($view->template);
    $this->assertEmpty($view->params);
    return $view;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  artax\views\ArtaxView::setVar
   */
  public function testSetVarAssignsTemplatePropertyValue($view)
  {
    $myVar = 'my value';
    $view->setVar('myVar', $myVar);
    $this->assertEquals($myVar, $view['myVar']);
    return $view;
  }
  
  /**
   * @depends testSetVarAssignsTemplatePropertyValue
   * @covers  artax\views\ArtaxView::getVar
   */
  public function testGetVarReturnsTemplatePropertyValue($view)
  {
    $myVar = 'my value';
    $this->assertEquals($myVar, $view->getVar('myVar'));
    return $view;
  }
  
  /**
   * @depends testGetVarReturnsTemplatePropertyValue
   * @covers  artax\views\ArtaxView::setTemplate
   */
  public function testSetTemplateAssignsPropertyValue($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $view->setTemplate($tpl);
    $this->assertEquals($tpl, $view->template);
    return $view;
  }
  
  /**
   * @depends testSetTemplateAssignsPropertyValue
   * @covers  artax\views\ArtaxView::render
   * 
   * Template contents: Template value: <?php echo $myVar; ?>
   */
  public function testRenderGeneratesExpectedOutput($view)
  {
    $expected = 'Template value: my value';
    $this->assertEquals($expected, $view->render());
  }
  
  /**
   * @depends testGetVarReturnsTemplatePropertyValue
   * @covers  artax\views\ArtaxView::render
   * @expectedException artax\exceptions\ErrorException
   */
  public function testRenderThrowsExceptionOnError($view)
  {
    $tpl = 'vfs://myapp/views/bad_template.php';
    $view->setTemplate($tpl);
    $view->render();
  }
  
  /**
   * @depends testSetVarAssignsTemplatePropertyValue
   * @covers  artax\views\ArtaxView::output
   * 
   * Template contents: Template value: <?php echo $myVar; ?>
   */
  public function testOutputEchoesRenderedTemplate($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $view->setTemplate($tpl);
    $expected = 'Template value: my value';
    ob_start();
    $view->output();
    $output = ob_get_contents();
    ob_end_clean();
    $this->assertEquals($expected, $output);
  }
}

class ArtaxViewTestImplementation extends artax\views\ArtaxView
{
  public function __get($prop)
  {
    if (property_exists($this, $prop)) {
      return $this->$prop;
    } else {
      $msg = 'Invalid property: ' . __CLASS__ . "::\$$prop does not exist";
      throw new OutOfBoundsException($msg);
    }
  }
}
