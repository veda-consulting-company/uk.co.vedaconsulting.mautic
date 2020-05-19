<?php
/**
 * @file
 * Exception for all Mautic API operations that result in a 5xx error, or do
 * not return JSON (network timeouts).
 */
class CRM_Mautic_NetworkErrorException extends CRM_Mautic_Exception_APIException {}
