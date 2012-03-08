<?php

class ArtaxViewTest extends PHPUnit_Framework_TestCase
{
  public function testBeginsEmpty()
  {
    $view = new ArtaxViewTestImplementation;
    $this->assertEmpty($view->params);
    return $view;
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  Artax\Views\ArtaxView::setVar
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
   * @covers  Artax\Views\ArtaxView::getVar
   */
  public function testGetVarReturnsTemplatePropertyValue($view)
  {
    $myVar = 'my value';
    $this->assertEquals($myVar, $view->getVar('myVar'));
    return $view;
  }
  
  /**
   * @depends testGetVarReturnsTemplatePropertyValue
   * @covers  Artax\Views\ArtaxView::render
   * 
   * Template contents: Template value: <?php echo $myVar; ?>
   */
  public function testRenderGeneratesExpectedOutput($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $expected = 'Template value: my value';
    $this->assertEquals($expected, $view->render($tpl));
  }
  
  /**
   * @depends testGetVarReturnsTemplatePropertyValue
   * @covers  Artax\Views\ArtaxView::render
   * @expectedException ErrorException
   */
  public function testRenderThrowsExceptionOnError($view)
  {
    $tpl = 'vfs://myapp/views/bad_template.php';
    $view->render($tpl);
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  Artax\Views\ArtaxView::render
   */
  public function testRenderAssignsTemplatePropertyIfSpecified($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $myVar = 'my value';
    $view->setVar('myVar', $myVar);
    $expected = 'Template value: my value';
    $this->assertEquals($expected, $view->render($tpl));
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  Artax\Views\ArtaxView::render
   */
  public function testRenderAssignsVarsIfSpecified($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $myVars = ['myVar' => 'my value', 'myOtherVar' => 'my other value'];
    $rendered = $view->render($tpl, $myVars);
    $this->assertEquals($myVars, $view->all());
  }
  
  /**
   * @depends testBeginsEmpty
   * @covers  Artax\Views\ArtaxView::setAll
   */
  public function testSetAllAssignsMultipleTplVarsAtOnce($view)
  {
    $myVars = ['myVar' => 'my value', 'myOtherVar' => 'my other value'];
    $returned = $view->setAll($myVars);
    $this->assertEquals($myVars, $view->all());
    $this->assertEquals($view, $returned);
  }
  
  /**
   * @depends testSetVarAssignsTemplatePropertyValue
   * @covers  Artax\Views\ArtaxView::output
   * 
   * Template contents: Template value: <?php echo $myVar; ?>
   */
  public function testOutputEchoesRenderedTemplate($view)
  {
    $tpl = 'vfs://myapp/views/my_template.php';
    $expected = 'Template value: my value';
    ob_start();
    $view->output($tpl);
    $output = ob_get_contents();
    ob_end_clean();
    $this->assertEquals($expected, $output);
  }
}

class ArtaxViewTestImplementation extends Artax\Views\ArtaxView
{
  use MagicTestGetTrait;
}
