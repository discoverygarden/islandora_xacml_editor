<?php
namespace Drupal\islandora_xacml_api;

/**
 * This class is how programmers should interact with Xacml objects.
 *
 * It takes either XACML XML as a string or no arguements and creates a blank
 * XACML object. The interaction with the rules takes place through member
 * object of this class. For instance to add roles that can manage the object:
 *
 * @code
 *   xacml = new Xacml()
 *   // allow userA to manage the object
 *   xacml->managementRule->addUser('userA')
 *   // allow roleC and roleD to manage the object
 *   xacml->managementRule->addRole(array('roleC', 'roleD'))
 * @endcode
 */
// @codingStandardsIgnoreLine
class Xacml {

  /**
   * The $xacml datastructure parsable by XacmlWriter and XacmlParser.
   *
   * @var array
   */
  protected $xacml;
  /**
   * Rule to allow anything. Users shouldn't need to interact with this.
   *
   * @var XacmlPermitEverythingRule
   */
  protected $permitEverythingRule;

  /**
   * Rule controling who can manage the object with this XACML policy.
   *
   * @var XacmlManagementRule
   */
  public $managementRule;
  /**
   * Rule controlling who can view specific datastreams and mimetypes.
   *
   * @var XacmlDatastreamRule
   */
  public $datastreamRule;
  /**
   * Rule controlling who can view datastreams in this object.
   *
   * @var XacmlViewingRule
   */
  public $viewingRule;

  /**
   * Initializes the $xacml datastructure that can be parsed with XacmlWriter.
   *
   * @return array
   *   Shell array representing an XACML object.
   */
  protected function initializeXacml() {
    // Create the rule array.
    $xacml = [
      'RuleCombiningAlgId' => 'urn:oasis:names:tc:xacml:1.0:rule-combining-algorithm:first-applicable',
      'rules'              => [],
    ];
    return $xacml;
  }

  /**
   * The constructor for the XACML object. Initialize new XACML object.
   *
   * @param string|null $xacml
   *   The XACML XML as a string. If this isn't passed the constructor will
   *   instead create a new XACML object that permits everything.
   *
   * @throws XacmlException
   *   When the XML cannot be parsed.
   */
  public function __construct($xacml = NULL) {

    $management_rule = NULL;
    $datastream_rule = NULL;
    $viewing_rule = NULL;

    if ($xacml != NULL) {
      $this->xacml = XacmlParser::parse($xacml);

      // Decide what is enabled.
      foreach ($this->xacml['rules'] as $rule) {
        if ($rule['ruleid'] == ISLANDORA_XACML_API_MANAGEMENT_RULE) {
          $management_rule = $rule;
        }
        elseif ($rule['ruleid'] == ISLANDORA_XACML_API_DATASTREAM_RULE) {
          $datastream_rule = $rule;
        }
        elseif ($rule['ruleid'] == ISLANDORA_XACML_API_VIEWING_RULE) {
          $viewing_rule = $rule;
        }
      }
    }
    else {
      $this->xacml = $this->initializeXacml();
    }

    $this->datastreamRule = new XacmlDatastreamRule($datastream_rule, $this);
    $this->managementRule = new XacmlManagementRule($management_rule, $this);
    $this->viewingRule = new XacmlViewingRule($viewing_rule, $this);
    $this->permitEverythingRule = new XacmlPermitEverythingRule($this);
  }

  /**
   * Updates the rules array before it is passed to XacmlWriter.
   *
   * @note
   *   It takes into account which rules have been populated.
   */
  protected function updateRulesArray() {
    $this->xacml['rules'] = [];

    if ($this->datastreamRule->isPopulated()) {
      $this->xacml['rules'][] = $this->datastreamRule->getRuleArray();
    }
    if ($this->managementRule->isPopulated()) {
      $this->xacml['rules'][] = $this->managementRule->getRuleArray();
    }
    if ($this->viewingRule->isPopulated()) {
      $this->xacml['rules'][] = $this->viewingRule->getRuleArray();
    }
    $this->xacml['rules'][] = $this->permitEverythingRule->getRuleArray();
  }

  /**
   * Returns the DomDocument that is associated with this Xacml Rule.
   *
   * @return DomDocument
   *   The DomDocument associated with this Xacml rule.
   */
  public function getDomDocument() {
    $this->updateRulesArray();
    return XacmlWriter::toDom($this->xacml);
  }

  /**
   * Returns a string containing the XML for this XACML policy.
   *
   * @param bool $pretty_print
   *   If set to TRUE the function will return a prettyprinted xacml policy.
   *
   * @return string
   *   String containing XACML XML.
   */
  public function getXmlString($pretty_print = TRUE) {
    $this->updateRulesArray();
    return XacmlWriter::toXml($this->xacml, $pretty_print);
  }

}
