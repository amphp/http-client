<?php

namespace Controllers {
  
  /**
   * The base controller from which all other controllers inherit
   * 
   * Implements the `NotifierInterface` to enable event handling from within
   * controllers.
   */
  class Test extends ControllerAbstract
  {
    /**
     * The controller's "work" method
     */
    public function exec()
    {
      $this->response->set('Output sent by Controllers\Test');
      return $this;
    }
  }
}
