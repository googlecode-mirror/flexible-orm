<?php
namespace ORM\Tests;
use \ORM\Tests\Mock, \ORM\SDB\ORMModelSDB;

require_once 'ORMTest.php';


class ORMModelSDBTest extends ORMTest {
    /**
     * @var AmazonSDB $object
     */
    protected $object;

    const DOMAIN = 'cars';

    private $_testCars = array(
            '1' => array('brand' => 'Alfa Romeo', 'colour' => 'Blue',  'doors' => 4),
            '2' => array('brand' => 'Volkswagen', 'colour' => 'Black', 'doors' => 5),
            '3' => array('brand' => 'Volkswagen', 'colour' => 'Grey',  'doors' => 2),
        );

    protected function setUp(){
        $this->object = new \AmazonSDB();
        $this->object->set_response_class('\ORM\SDB\SDBResponse');
        $this->object->set_region(\AmazonSDB::REGION_APAC_SE1);

        $this->object->create_domain(self::DOMAIN);
        $this->object->batch_put_attributes(self::DOMAIN, $this->_testCars);

    }

    protected function tearDown() {
        $items = $this->object->select("SELECT * FROM ".self::DOMAIN." WHERE brand = 'Ford'");
        foreach($items as $id => $item ) {
            $this->object->delete_attributes(self::DOMAIN, $id);
        }
    }

    public function testReturnsSDBResponse() {
        $this->assertEquals( 'ORM\SDB\SDBResponse', get_class($this->object->list_domains() ) );
    }

    public function testFind() {
        $car = Mock\SDBCar::Find(3);

        $this->assertEquals( 'ORM\Tests\Mock\SDBCar', get_class($car) );
        $this->assertEquals( 'Volkswagen', $car->brand );
        $this->assertEquals( '3', $car->id() );
    }

    public function testFindBy() {
        $car = Mock\SDBCar::FindByBrand('Alfa Romeo');

        $this->assertEquals( 'ORM\Tests\Mock\SDBCar', get_class($car) );
        $this->assertEquals( 'Alfa Romeo', $car->brand );
        $this->assertEquals( '1', $car->id() );
    }

    public function testFindAll() {
        $cars = Mock\SDBCar::FindAll();

        $this->assertEquals( 3, count($cars) );
        $this->assertEquals( 'ORM\ModelCollection', get_class($cars) );
        $this->assertEquals( 'ORM\Tests\Mock\SDBCar', get_class($cars[1]) );
    }

    public function testFindAllBy() {
        $cars = Mock\SDBCar::FindAllByBrand('Volkswagen');

        $this->assertEquals( 'ORM\ModelCollection', get_class($cars) );
        $this->assertEquals( 2, count($cars) );

        foreach( $cars as $car ) {
            $this->assertEquals( 'Volkswagen', $car->brand );
        }
    }

    /**
     * Test the situation where one placeholder contains the name of another placeholder
     * and the short placeholder is bound first
     */
    public function testFindBySimilarPlaceholderNames() {
        $car = Mock\SDBCar::Find(array(
            'where'     => 'name = :brand OR brand = :brandname',
            'values'    => array(
                ':brand'     => 'Volkswagen',
                ':brandname' => 'Volkswagen'
            )
        ));

        $this->assertEquals( 'Volkswagen', $car->brand);
    }

    public function testSaveCreate() {
        $car            = new Mock\SDBCar();
        $car->brand     = 'Ford';
        $car->colour    = 'Black';
        $car->doors     = 2;
        $car->privateTest( 'changing private attribute' );

        $this->assertTrue( $car->save() );
        $this->assertNotNull( $car->id() );

        $storedCar = Mock\SDBCar::Find($car->id());
        $this->assertEquals($car->brand,    $storedCar->brand, 
                "Stored Car ($car) brand does not match created one" );
        $this->assertEquals($car->colour,   $storedCar->colour,
                "Stored Car ($car) colour does not match created one" );
        $this->assertEquals($car->doors,    $storedCar->doors,
                "Stored Car ($car) doors do not match created one" );
        $this->assertEquals($car->id(),     $storedCar->id(),
                "Stored Car ($car) id() does not match created one" );
        $this->assertNotEquals('changing private attribute', $storedCar->privateTest() );
    }

    public function testSaveUpdate() {
        $car            = new Mock\SDBCar();
        $car->brand     = 'Ford';
        $car->colour    = 'Blue';
        $car->doors     = 8;
        $car->save(); // Create

        $this->assertEquals( 'Blue', Mock\SDBCar::Find($car->id())->colour );

        $car->colour = 'Red';
        $car->doors  = 6;
        $car->save(); // Update!

        $storedCar = Mock\SDBCar::Find($car->id());
        $this->assertEquals( $car->id(), $storedCar->id() );
        $this->assertEquals( $car->brand, $storedCar->brand ); // check the unchanged value
        $this->assertEquals( 'Red', $storedCar->colour ); // Check the updated values
        $this->assertEquals( '6', $storedCar->doors );
    }

    public function testDelete() {
        $car            = new Mock\SDBCar();
        $car->brand     = 'Ford';
        $car->colour    = 'Blue';
        $car->doors     = 8;
        $this->assertTrue( $car->save(), "Unable to save car for deletion!" );

        $car->delete();
        $this->assertFalse( Mock\SDBCar::Find($car->id()), "Found car when it should not exist" );
    }

    public function testUpdateChangeItemName() {
        $this->markTestIncomplete("Test not implemented");
    }

    public function testFindAllGetAll() {
        // Create all the owners
        // only do this once as it's extremely slow
//        for( $i=1; $i<=150; $i++) {
//            $owner = new Mock\SDBOwner();
//            $owner->name = "MyName".rand(1,200);
//            $owner->save();
//        }

        $allOwners = Mock\SDBOwner::FindAll();

        $this->assertGreaterThan( 149, count($allOwners) );
    }

    /**
     * 
     */
    public function testFindQuotes() {
        $owner = Mock\SDBOwner::Find(array(
            'where'  => "name = 'o\''connel' OR name LIKE :name",
            'values' => array(':name' => 'MyName%')
        ));

        $this->assertEquals( 'ORM\Tests\Mock\SDBOwner', get_class($owner),
            "Find was confused by escaped single quote in WHERE"
        );

        $owner = Mock\SDBOwner::Find(array(
            'where'  => "name = :first OR name LIKE :name",
            'values' => array(':name' => 'MyName%', ':first' => "o'connel")
        ));

        $this->assertEquals( 'ORM\Tests\Mock\SDBOwner', get_class($owner),
            "Find was confused by single quote in placeholder"
        );
    }

    public function testCreateComma() {
        $owner = new Mock\SDBOwner();
        $owner->name = "This is, a silly";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }

    public function testUpdateComma() {
        $owner = Mock\SDBOwner::Find();
        $owner->name = "This is, a silly";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }

    public function testCreateComplex() {
        $owner = new Mock\SDBOwner();
        $owner->name = "This i's, \na =  silly";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }

    public function testUpdateComplex() {
        $owner = Mock\SDBOwner::Find();
        $owner->name = "This \ni's', 'a silly\\\\',";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }

    public function testEscapeCreate() {
        $owner = new Mock\SDBOwner();
        $owner->name = "Th''is i's a \'silly";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }

    public function testEscapeUpdate() {
        $owner = Mock\SDBOwner::Find();
        $owner->name = "This i's a \'silly";

        $this->assertTrue( $owner->save() );

        $fetched = Mock\SDBOwner::Find( $owner->id() );
        $this->assertEquals( $owner->name, $fetched->name );
    }
}
?>