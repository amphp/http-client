<?php

trait MagicTestGetTrait
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
