# bash.class
Класс для составления запросов к командной оболочке bash для последующего их выполнения при помощи команд PHP:
  - __exec()__ 
  - __system()__
  - __shell_exec()__

#### Используемые приложения

  - [SQL*Plus](http://www.oracle.com/technetwork/topics/linuxx86-64soft-092277.html)
  - [Архиватор 7-zip](http://www.7-zip.org/download.html)
  - FTP
  - MAIL

#### Доступные методы

  - SQLtoCSV
  - archive
  - sendToFTP
  - deleteFromFTP
  - mailSend

#### Константы для подключения к БД

  - ORACLE_BASE
  - ORACLE_USER
  - ORACLE_PSWD

# bash::SQLtoCSV
Используется для получения __CSV__ файла по __SQL-запросу__
  - кодировка возвращаемого файла __cp1251__
  - заголовки обернуты в «__"__» двойные кавычки
  - разделитель между записями $colsep = ";"
  - $heading = true : передавать заголовки в csv файл
  - $updateSQL содержит простой SQL запрос вида: __UPDATE table SET last_upload_file WHERE id__ для добавления в БД записи о последнем созданном файле
  
```php
bash::SQLtoCSV(str $sql, arr $header, str $filename, str $path[, bool $heading[, str $updateSQL[, str $colsep]]]);
```

#### Пример
> Если массив __$header__ не пустой, в исходный __SQL-запрос__ между словами __SELECT__ и __FROM__ подставляются ключи массива,
> а в результирующий __CSV__ файл в качестве заголовков записываются соответствующие этим ключам значения

Обращение к методу
```php
$header = array(
    'ID'   => 'ID',
    'name' => 'Имя',
    'mail' => 'Почта'
    );
```
```php
$csv = bash::SQLtoCSV('SELECT * FROM tableName', $header, 'report.csv', '/var/tmp/');
```
Эквивалентно обращению

```php
$csv = bash::SQLtoCSV('SELECT ID, name Имя, mail as "Почта" FROM tableName', $header = array() , 'report', 'var/tmp');
```
> Внимание! Запрос вида "__SELECT * FROM tableName__" при условии что __empty($header) == true__ может вернуть корректный результат с именами столбцов из БД в шапке, но скорость построения CSV будет обратно пропорциональна сложности запроса

# bash::archive
Используется для архивации файлов
  - допустимые форматы: __zip__ и __7z__
  - можно создать архив с паролем
  
```php
bash::archive(string $filename, string $path[, string $type[, string $password]]);
```

#### Пример

```php
$archive = bash::archive("report.csv", 'var/tmp', 'zip', 'p@$sw0Rd');
```
> Внимание! Если не указать __$type__, по умолчанию будет создан __7z__-архив

# bash::sendToFTP
Используется для отправки файлов на FTP
```php
bash::sendToFTP(str $server, str $login, str $password, str $filename, str $path, str $ftpPath[, int $port]);
```
#### Пример

```php
$FTPut = bash::sendToFTP('127.0.0.1', 'login', 'p@$sw0Rd', 'report.7z', 'var/tmp', 'Out/attempt');
```
# bash::deleteFromFTP
Используется для удаления файлов с FTP
```php
bash::deleteFromFTP(str $server, str $login, str $password, str $filename, str $path, str $ftpPath[, int $port]);
```
#### Пример

```php
$FTPdel = bash::deleteFromFTP('127.0.0.1', 'login', 'p@$sw0Rd', 'report.7z', 'Out/attempt');
```
# bash::mailSend
Используется для отправки писем
```php
bash::mailSend(string $to[, string $header[, string $message[, string $filename, string $path[, string $from]]]])
```
#### Пример
Отправка на два адреса, без вложения файла c подменой адреса отправителя
```php
$mail = bash::mailSend('first@mail.ru, second@mail.ru', 'Тема письма', 'Текст сообщения', null, null, 'from@mail.ru');
```
С отправкой файла, но без подмены адреса отправителя, темы и текста письма 
```php
$mail = bash::mailSend('you@mail.ru', null, null, null, 'report.7z', 'var/tmp');
```

# Выполнение комбинированного запроса
> Используя переменные, созданные в предыдущих примерах, можно выполнить следующие запросы:

Создание файла и отправка письма на e-mail
```php
system($csv . $mail);
```
Создание файла, архивация и отправка на FTP
```php
exec($csv . $archive . $FTPut);
```
Создание файла, удаление и отправка на файла на FTP
```php
shell_exec($csv . $FTPdel . $FTPut);
```
### Версия
1.0.0
