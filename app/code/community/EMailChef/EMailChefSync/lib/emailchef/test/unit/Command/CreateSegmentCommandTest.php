  <?php

use EMailChef\Command\Api\CreateSegmentCommand;

class CreateSegmentCommandTest extends PHPUnit_Framework_TestCase
{
    /**
   * @group apicalls
   */
  public function testExecute()
  {
      $c = new CreateSegmentCommand();
      $result = $c->execute('850251f2699d2df00a66486739b1c2bf3130b049', 1254, 'newgroup2', 'newgroup desc', 'AND');
    /*$result = $c->execute('3bfb70f683c1c23ba27d89aff468ee02ed1b345f',1254,'newgroup','newgroup desc','AND',array(
      "logic"=>"OR",
      "conditions"=>array(

        array(
          "field_id"=>"-1",
                  "name"=>"email",
                  "comparable_id"=>null,
                  "comparator_id"=>"3",
                  "value"=>"n@n.it"
        )

      ),
    ));*/
    $this->assertInternalType('string', $result);
  }
}
