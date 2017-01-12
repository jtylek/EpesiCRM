<?php
trait toHtml {
    abstract function getFrozenHtml();
    abstract function getHtml();
    abstract function isFrozen();
    public function toHtml() {
        if ($this->isFrozen()) {
            return $this->getFrozenHtml();
        } else {
            return $this->getHtml();
        }
    }
}