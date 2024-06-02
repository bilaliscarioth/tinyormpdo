<?php


// IMPORT http_build_query code
// Refactor it into a parser of K,V arrays to SQL conditions.
function into_sql(array $formdata)
{
    // If $formdata is an object, convert it to an array
    if (is_object($formdata)) {
        $formdata = get_object_vars($formdata);
    }

    // Check we have an array to work with
    if (!is_array($formdata)) {
        throw HTTPException('http_build_query() Parameter 1 expected to be Array or Object. Incorrect value given.',
            501);
        return false;
    }

    // If the array is empty, return null
    if (empty($formdata)) {
        return;
    }

    // Argument seperator
    $separator = " AND ";

    // Start building the query
    $tmp = array ();
    foreach ($formdata as $key => $val) {
        if (is_null($val)) {
            continue;
        }

        if (is_scalar($val)) {
            if (is_int($val)) {
                array_push($tmp, $key . '=' . $val);
            } else {
                array_push($tmp, $key . '=' . "'".$val."'");
            }
            continue;
        }

        // If the value is an array, recursively parse it
        if (is_array($val) || is_object($val)) {
            array_push($tmp, http_build_query($val, urlencode($key)));
            continue;
        }

        // The value is a resource
        return null;
    }

    return implode($separator, $tmp);
}


class ORMObject {
    public int $id = -1; // Needed
    public string $table;

    public function __construct(string $table) {
        if (empty($table)){
            throw new Exception("");
        }

        $this->table = $table;
    }

    // On retire les variables en trop lors de nos conditions.
    public static function filter_array_class ($k){
        return array_key_exists($k, get_class_vars(get_called_class()));
    }

    public static function find_one(array $find) {
        $classname = get_called_class();

        if ($classname === get_class()){
            // TODO do exception;
            return null;
        }

        if (empty($find)) {
            // TODO do exception;
            return null;
        }

        // On filtre le find avec
        $find = array_filter($find, $classname."::filter_array_class", ARRAY_FILTER_USE_KEY );
        global $db;

        $self = new $classname();
        $condition = into_sql($find);

        $req = $db->prepare("SELECT * FROM $self->table WHERE $condition");
        $req->setFetchMode(PDO::FETCH_CLASS, $classname);
        $req->execute();

        return $req->fetch(PDO::FETCH_CLASS);
    }

    public static function delete(array $find) {
        $classname = get_called_class();

        if ($classname === get_class()){
            // TODO do exception;
            return null;
        }

        if (empty($find)) {
            // TODO do exception;
            return null;
        }

        // On filtre le find avec
        $find = array_filter($find, $classname."::filter_array_class", ARRAY_FILTER_USE_KEY );
        global $db;

        $self = new $classname();
        $condition = into_sql($find);

        $req = $db->prepare("DELETE FROM $self->table WHERE $condition");
        $req->setFetchMode(PDO::FETCH_CLASS, $classname);
        $req->execute();

        return $req->fetch(PDO::FETCH_CLASS);
    }
    
	public static function count(array $find = null){
        $classname = get_called_class();

        if ($classname === get_class()){
            // TODO do exception;
            return null;
        }

        global $db;

        if (isset($find)) {
            // On filtre le find avec
            $find = array_filter($find, $classname."::filter_array_class", ARRAY_FILTER_USE_KEY );
        }

        $self = new $classname();
        $condition = "";
        if (empty($find)){
            $req = $db->prepare("SELECT COUNT(*) FROM $self->table ");
        } else {
            $condition = into_sql($find);
            $req = $db->prepare("SELECT COUNT(*) FROM $self->table WHERE $condition");
        }

        $req->execute();
        
        //echo var_dump($req->fetchAll());

        return $req->fetch()[0];
    }

    public static function find_all(array $find = null){
        $classname = get_called_class();

        if ($classname === get_class()){
            // TODO do exception;
            return null;
        }

        global $db;

        if (isset($find)) {
            // On filtre le find avec
            $find = array_filter($find, $classname."::filter_array_class", ARRAY_FILTER_USE_KEY );
        }

        $self = new $classname();
        $condition = "";
        if (empty($find)){
            $req = $db->prepare("SELECT * FROM $self->table ");
        } else {
            $condition = into_sql($find);
            $req = $db->prepare("SELECT * FROM $self->table WHERE $condition");
        }

        $req->setFetchMode(PDO::FETCH_CLASS, $classname);
        $req->execute();

        return $req->fetchAll();
    }


    public function save() {
        global $db;
        $attributs = get_class_vars(get_called_class());
        unset($attributs['table']);
        if ( $this->id == -1){
            unset($attributs['id']);
        }

        $att_str = implode(',', array_keys($attributs));
        $val_att = "(";
        foreach(array_keys($attributs) as $name) {
            if ($this->id > -1) {
                $req = $db->prepare("UPDATE $this->table SET $name = '".$this->$name."'  WHERE id= $this->id ;");
                $req->execute();
            }
            echo var_dump($this);
            if (is_string($this->$name)){
                $val_att .= "'".$this->$name."'";
            }else {
                $val_att .= $this->$name;
            }
            if ($name !== array_key_last($attributs)){
                $val_att .= ",";
            }
        }
        $val_att .= ")";

        if ($this->id == -1) {
            $req = $db->prepare("INSERT INTO $this->table ($att_str) VALUES $val_att;");
            $req->debugDumpParams();
            $req->execute();
        }
    }

    public function __toString() :string {
        $classname = get_called_class();

        if ($classname === get_class()){
            // TODO do exception;
            return null;
        }

        $attributs = get_class_vars($classname);

        $html_code = "<div class=\"w3-container w3-card w3-border w3-col\">\n";
            foreach(array_keys($attributs) as $value) {
                if ( $value === "table" || $value === "id" ) continue;
                $html_code .= "<div class=\"w3-left-align\">".$value.": ".$this->$value."</div class=\"w3-left-align\">";
            }
        $html_code .= "\n</div>\n";

        return $html_code;
    }
};

// On évite les includes circulaires....
include './classes/objects/category.php';
include './classes/objects/vote.php';
include './classes/objects/user.php';
include './classes/objects/post.php';

?>
