<?php
/**
 * @file
 * @author jarrod.swift
 */
namespace FlexibleORMTests\Mock;
use ORM\ORM_Model;
/**
 * Description of Car
 *
 * A simple Model showing a custom foreign key.
 */
class BadModel extends ORM_Model {
    const TABLE = 'non-existant';
}
?>
