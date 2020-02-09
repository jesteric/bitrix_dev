<?php
// пока используется для пользователей но будет использоваться везде для подключения к лисе, чтобы подключение держать в одном месте, хотелось бы но чувствую не будет времени :)
class baseIntegrationFox{
    const IBLOCK = '2'; // каталог товаров
    const IBLOCK_SKU = '3'; // каталог торговых предложений
    const USER_FOR_INTEGRATION = '4'; // пользователь для интеграции
    // доступы
    const HOST = '82.209.254.214,11433'; 
    const DB = 'Gpartner';
    const USER = 'MediaLine';
    const PASS = 'Medi@Line';
    const CHARSET = 'utf8';
    
    protected $logFile; // путь к логу ошибок
    protected $tmpFilesDir; // путь к файлам в которых будет лежать результат запроса для тестов
    
    public function __construct($iBlock = self::IBLOCK, $iBlockSku = self::IBLOCK_SKU, $dirImageTemp = '',$testMode = 0, $isGoods = 1)
    {
        $this->logFile = $_SERVER["DOCUMENT_ROOT"].'/export/files/log/import-log-'.date('d-m-y-H-i-s').'.log';
        $this->tmpFilesDir = '/export/files/';       
    }
    //получаем пользователя для интеграции
    public static function getUserIntegration(){
        return self::USER_FOR_INTEGRATION;
    }
    // получаем доступы из констант
    public static function getUserCreditans(){
        $arCreditans['user'] = self::USER;
        $arCreditans['db'] = self::DB;
        $arCreditans['pass'] = self::PASS;
        $arCreditans['charset'] = self::CHARSET;
        $arCreditans['host'] = self::HOST;
        return $arCreditans;
    }
    //собсна сам запрос к лисе 
    /* 
    вкратце получаем запрос, уникальное поле по которому группировать массив (если $needTree - истина)
    либо в ключ массива будет браться данное значение.
    */
    
    public function getQuery($query, $unigId, $needTree = 0){
        
        $arCreditans = $this->getUserCreditans(); 
        try{
            $db = new PDO("sqlsrv:Server={$arCreditans['host']};Database={$arCreditans['db']}", $arCreditans['user'], $arCreditans['pass']);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }catch (PDOException $e) {
            $db=null;
            file_put_contents($this->logFile, 'Ошибка связи с лисой' . $e->getMessage() . "\r\n", FILE_APPEND | LOCK_EX);
            file_put_contents($this->logFile, 'Import::END - ' . date('d.m.Y H:i:s') . "\r\n", FILE_APPEND | LOCK_EX);
            die();
        }
        try{ 
            $stmt = $db->query($query);
            while ($row = $stmt->fetch())
            { 
                $countRow = count($row)-1;
                for($i = 0;$i <= $countRow;$i++){
                    unset($row[$i]); //удаляем дубли
                }
                foreach($row as $key => $item){
                    $row[$key] = trim($item); // удаляем лишние пробелы
                }
                if($unigId){
                    if($needTree){
                        $arBase[$row[$unigId]][] = $row;
                    }else{
                        $arBase[$row[$unigId]] = $row; 
                    } 
                }else{
                    $arBase[] = $row;
                }
                
                  
            }
        }catch(PDOException $e){ 
            file_put_contents($this->logFile, 'Ошибка в запросе к лисе' . $e->getMessage() . "\r\n", FILE_APPEND | LOCK_EX);
            file_put_contents($this->logFile, 'Import::END - ' . date('d.m.Y H:i:s') . "\r\n", FILE_APPEND | LOCK_EX);
            $db = null;
            die();
        }
        $db = null; // закрываем соединение 
        return $arBase;
    }
    /* метод нужен только для отладки для работы с временным файлом вместо прямого запроса к лисе
    уменьшает время разработки */
    
    protected function getAllFromFox($query, $unigId, $file, $needTree=0){
         if($this->testMode && file_exists($this->getPathToFile($file))){
             $json = file_get_contents($this->getPathToFile($file));
             $arSection = json_decode($json, true);
         }else{             
             $arSection = $this->getQuery($query, $unigId , $needTree);
             if($this->testMode){ //записываем данные в файл
                $this->saveToLocal($this->getPathToFile($file), json_encode($arSection));
             }elseif(file_exists($this->getPathToFile($file))){
                 unlink($this->getPathToFile($file)); // если не тест то удаляем, чтобы не висело.
             } 
         }
         return $arSection;
    }
    // ну тут все понятно
    protected function saveToLocal($filePath, $data)
    {
        $fp = fopen($filePath, "w"); // Открываем файл в режиме записи
        $test = fwrite($fp, $data); // Запись в файл
        fclose($fp); //Закрытие файла
    }
    protected function getPathToFile($file_name){
        return $_SERVER["DOCUMENT_ROOT"] . $this->tmpFilesDir . $file_name; 
    }
}
