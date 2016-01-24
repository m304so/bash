<?php
/**
 * Класс для получения шелл скрипта для функции exec
 *
 * Добавляет возможность сохранять отчеты Oracle в формате csv через SQLPlus
 * Архивировать с паролем в 7z и zip
 * Отправлять файлы на FTP
 * Удалять файлы с FTP
 * Отправлять уведомление на почту
 *
 * @author Sergey Medvedev <m304so@yandex.ru>
 */
class bash {

    private static function SQLplusConnection() {
        $connection = 'sqlplus -S ' . ORACLE_USER . '/' . ORACLE_PSWD . '@' . ORACLE_BASE;
        return $connection;
    }

    private static $encoding = array(
        "export NLS_LANG=English_America.AL32UTF8",
        "export LANG=ru_RU.utf8",
    );

    private static $SQLplusDefaultParams = array(
        "heading" => "off",
        "feedback" => "off",
        "newpage" => "none",
        "echo" => "off",
        "termout" => "off",
        "wrap" => "on",
        "trimspool" => "on",
        "trimout" => "on",
        "linesize" => "32767",
    );
    private static $SQLplusGetHeadersParams = array(
        "heading" => "on",
        "embedded" => "on",
        "pages" => "0",
        "newpage" => "0",
        "feedback" => "off",
        "wrap" => "on",
        "linesize" => "50",
    );

    public static $SQLplusParams = array();

    private static $archiveType = array(
        '7z' => '-t7z',
        'zip' => '-tzip'
    );

    private static function validatePath($path = null) {
        if (is_null($path)) {
            return 'Не указан путь';
        }
        $path = ($path[0] != '/') ? '/' . $path : $path;
        $path = (strlen($path) - 1 == strripos($path, '/')) ? $path : $path . '/';
        return $path;
    }

    private static function setSQLplusParams($params = null, $request = null) {
        if (is_null($params)) {
            $params = self::$SQLplusParams;
        }
        foreach ($params as $key => $value) {
            $request .= "set " . $key . ' ' . $value . "\n";
        }
        return $request;
    }

    private static function getColumns($sql, $columns = array()) {
        $request = 'echo "';
        $request .= self::setSQLplusParams(self::$SQLplusGetHeadersParams);
        $request .= (strlen($sql) - 1 == strripos($sql, ';')) ? substr($sql, 0, -1) : $sql;
        $request .= ' where rownum < 2;';
        $request .= '"|' . self::SQLplusConnection();

        exec($request, $data);

        foreach ($data as $key => $value) {
            if (preg_match('/^--+/', $data[$key + 1])) {
                $value = trim($value);
                if (strripos($value, ' ')) {
                    $tmp = explode(' ', $value);
                    foreach ($tmp as $value) {
                        array_push($columns, $value);
                    }
                } else {
                    array_push($columns, $value);
                }
            }
        }
        return $columns;
    }

    public static function SQLtoCSV($sql = null, $header = array(), $filename = null, $path = null, $heading = false, $updateSQL = null, $colsep = ";", $request = null) {
        if (is_null($sql)) {
            return 'Отсутствует SQL-запрос';
        } elseif (is_null($filename)) {
            return 'Не указано имя файла для экспорта';
        } elseif (is_null($path)) {
            return 'Не указан путь для сохранения файла';
        }

        foreach (self::$encoding as $encoding) {
            $request .= $encoding . "\n";
        }

        $request .= 'echo "';
        $request .= self::setSQLplusParams(self::$SQLplusDefaultParams);
        $request .= "alter session set NLS_NUMERIC_CHARACTERS = ', ';" . "\n";
        $filename = (strripos($filename, '.csv') > 0) ? $filename : $filename . '.csv';

        $path = self::validatePath($path);

        $request .= 'spool ' . $path . $filename . "\n";

        // Удалить переносы строк и лишние пробелы из запроса
        $sql = preg_replace(array('/\r/', '/\n/', '/\s+/'), array('', ' ', ' '), $sql);

        $innerColumns = substr($sql, 7, strpos(strtolower($sql), 'from') - 8);
        if (array_shift(array_slice($header, 0, 1)) == '' && $innerColumns == '*') {
            $header = self::getColumns($sql);
        } elseif ($innerColumns != '*') {
            $header = array();
            $tmp = explode(',', $innerColumns);
            for ($i = 0; $i < count($tmp); $i++) {
                $tmp[$i] = trim($tmp[$i]);
                if (strpos($tmp[$i], ' ') == 0) {
                    $header[$tmp[$i]] = $tmp[$i];
                } elseif (strpos(strtolower($tmp[$i]), 'as') == 0) {
                    $t = explode(' ', $tmp[$i]);
                    $header[$t[0]] = $t[1];
                } elseif (strpos($tmp[$i], '"') == 0) {
                    $t = explode(' as ', $tmp[$i]);
                    $header[$t[0]] = $t[1];
                } else {
                    $t = explode(' as ', $tmp[$i]);
                    $header[$t[0]] = str_replace('"', '', $t[1]);
                }
            }
        }

        $title = "SELECT '";
        foreach ($header as $value) {
            if ($value != end($header)) {
                $title .= '\"' . $value . '\";';
            } else {
                $title .= '\"' . $value . '\"' . "' FROM dual;";
            }
        }

        $query = '';
        foreach ($header as $key => $value) {
            if ($value != end($header)) {
                $query .= (is_numeric($key)) ? $value . " || '" . $colsep . "' || " : $key . " || '" . $colsep . "' || ";
            } else {
                $query .= (is_numeric($key)) ? $value : $key;
            }
        }
        $sql = str_replace($innerColumns, $query, $sql);
        $request .= ($heading) ? $title . "\n" : '';
        $request .= (strlen($sql) - 1 == strripos($sql, ';')) ? $sql : $sql . ';' . "\n";
        $request .= (is_null($updateSQL)) ? '' : "\n" . $updateSQL . ";" . "\n" . "commit;" . "\n";
        $request .= "spool off" . "\n";
        $request .= '"|' . self::SQLplusConnection() . "\n";
        $request .= 'iconv -f utf8 -t cp1251 ' . $path . $filename . ' -o ' . $path . basename($filename, '.csv') . '_tmp.csv' . "\n";
        $request .= 'sed s/$/\\\r/ ' . $path . basename($filename, '.csv') . '_tmp.csv' . ' >' . $path . $filename . "\n";
        $request .= 'rm -f ' . $path . basename($filename, '.csv') . '_tmp.csv' . "\n";
        return $request;
    }

    public static function rm($filename = null, $path = null) {
        if (!is_null($path) && !is_null($filename)) {
            $path = self::validatePath($path);
            $request = 'rm -f ' . $path . $filename . "\n";
        } else {
            return false;
        }
        return $request;
    }

    public static function archive($filename = null, $path = null, $type = '7z', $password = null, $request = null) {
        if (is_null($filename)) {
            return 'Не указано имя файла';
        } elseif (is_null($path)) {
            return 'Не указан путь к файлу';
        } else {
            $path = self::validatePath($path);
        }

        $request = '7za a ' . self::$archiveType[$type] . ' ';
        if (!is_null($password)) {
            $request .= '-p' . quotemeta($password) . ' ';
        }

        $request .= '"' . $path;
        if (strpos($filename, '.') > 0) {
            $request .= substr($filename, 0, strpos($filename, '.'));
        } else {
            $request .= $filename;
            $filename .= '.*';
        }
        $request .= '.' . $type . '" "' . $path . $filename . '"' . "\n";
        return $request;
    }

    public static function mailSend($to = null, $header = null, $message = null, $filename = null, $path = null, $from = null, $request = null) {
        if (is_null($to)) {
            return 'Не указан получатель';
        }
        $request = 'echo "' . $message . '" | mail';
        if (!is_null($filename) && !is_null($path)) {
            $path = self::validatePath($path);
            $request .= ' -a ' . $path . $filename;
        }
        if (!is_null($from)) {
            $from = ' -r "' . $from . '"';
        }
        $request .= ' -s "' . $header . '"' . $from . ' ' . $to . "\n";
        return $request;
    }

    public static function sendToFTP($server = null, $login = null, $password = null, $filename = null, $path = null, $ftpPath = null, $port = 21, $request = null) {
        if (is_null($server)) {
            return 'Не указан адрес FTP-сервера';
        } elseif (is_null($login)) {
            return 'Не указан логин FTP-сервера';
        } elseif (is_null($password)) {
            return 'Не указан пароль FTP-сервера';
        } elseif (is_null($filename)) {
            return 'Не указано имя файла для отправки на FTP';
        } elseif (is_null($path)) {
            return 'Не указан путь к файлу для отправки на FTP';
        } elseif (is_null($ftpPath)) {
            return 'Не указан путь на FTP, по которому необходимо осуществить выгрузку';
        }

        $path = self::validatePath($path);
        $ftpPath = self::validatePath($ftpPath);

        $request = "host='" . $server . "'" . "\n";
        $request .= "port='" . $port . "'" . "\n";
        $request .= "user='" . $login . "'" . "\n";
        $request .= "pass='" . $password . "'" . "\n";
        $request .= "file='" . $path . $filename . "'" . "\n";
        $request .= "ufile='" . $ftpPath . $filename . "'" . "\n";
        $request .= 'ftp -n $host $port <<SEND_TO_FTP' . "\n";
        $request .= 'quote USER $user' . "\n";
        $request .= 'quote PASS $pass' . "\n";
        $request .= "bin" . "\n";
        $request .= 'put $file $ufile' . "\n";
        $request .= "quit" . "\n";
        $request .= "SEND_TO_FTP" . "\n";
        return $request;
    }

    public static function deleteFromFTP($server = null, $login = null, $password = null, $filename = null, $path = null, $port = 21, $request = null) {
        if (is_null($server)) {
            return 'Не указан адрес FTP-сервера';
        } elseif (is_null($login)) {
            return 'Не указан логин FTP-сервера';
        } elseif (is_null($password)) {
            return 'Не указан пароль FTP-сервера';
        } elseif (is_null($filename)) {
            return 'Не указано имя файла для отправки на FTP';
        } elseif (is_null($path)) {
            return 'Не указан путь к файлу для отправки на FTP';
        }

        $path = self::validatePath($path);

        $request = "host='" . $server . "'" . "\n";
        $request .= "port='" . $port . "'" . "\n";
        $request .= "user='" . $login . "'" . "\n";
        $request .= "pass='" . $password . "'" . "\n";
        $request .= "file='" . $path . $filename . "'" . "\n";
        $request .= 'ftp -n $host $port <<DEL_TO_FTP' . "\n";
        $request .= 'quote USER $user' . "\n";
        $request .= 'quote PASS $pass' . "\n";
        $request .= "bin" . "\n";
        $request .= 'mdelete $file' . "\n";
        $request .= "quit" . "\n";
        $request .= "DEL_TO_FTP" . "\n";
        return $request;
    }

}
