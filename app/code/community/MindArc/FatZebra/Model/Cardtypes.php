<?php
class MindArc_FatZebra_Model_CardTypes 
{
  public function toOptionArray() {
    return array(
      array("value" => "VI", "label" => "VISA"),
      array("value" => "MC", "label" => "MasterCard"),
      array("value" => "AE", "label" => "American Express"),
      array("value" => "JCB", "label" => "JCB")
    );
  }
}