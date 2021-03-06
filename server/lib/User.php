<?php

/**
 * User definition.
 *
 * @version 1.0.0
 *
 * @internal
 */
class User
{
    /**
     * @var int User internal identifier
     */
    public $id;
    /**
     * @var string User login
     */
    public $login;
    /**
     * @var string Encrypted user password
     */
    public $password;
    /**
     * @var boolean User status
     */
    public $status;
    /**
     * @var string User email address
     */
    public $mail;
    /**
     * @var array User scope
     */
    public $scope;
    /**
     * @var string User language
     */
    public $language;
    /**
     * @var int User login attempts failed
     */
    public $loginAttemptFailed;
    /**
     * @var int Timestamp of the last user login attempt failed
     */
    public $lastLoginAttemptFailed;

    /**
     * Initializes a User object with his identifier.
     *
     * @param int $id User identifier
     */
    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = intval($id);
        }
    }

    /**
     * Return all users.
     *
     * @return array Users
     */
    public static function getAll()
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT * FROM `user`;');
        if ($query->execute()) {
            //return array of users
            return $query->fetchAll(PDO::FETCH_CLASS, 'User');
        }
        return [];
    }

    /**
     * Create user.
     *
     * @param string $error The returned error message
     *
     * @return bool True if the user is created
     */
    public function insert(&$error)
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('INSERT INTO `user` (`login`, `password`, `scope`, `status`, `mail`, `language`) VALUES ( :login, :password, :scope, :status, :mail, :language);');
        $query->bindValue(':login', $this->login, PDO::PARAM_STR);
        $query->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT), PDO::PARAM_STR);
        $query->bindValue(':scope', $this->scope, PDO::PARAM_STR);
        $query->bindValue(':status', $this->status, PDO::PARAM_BOOL);
        $query->bindValue(':mail', $this->mail, PDO::PARAM_STR);
        $query->bindValue(':language', $this->language, PDO::PARAM_STR);
        if ($query->execute() && $query->rowCount() > 0) {
            $this->id = $connection->lastInsertId();
            //return true to indicate a successful user creation
            return true;
        }
        $error = $query->errorInfo()[2];
        //try to return intelligible error
        if ($query->errorInfo()[1] === 1062 || $query->errorInfo()[2] === 'UNIQUE constraint failed: user.login') {
            $error = ' : login `'.$this->login.'` already exists';
        }
        //return false to indicate an error occurred while creating user
        return false;
    }

    /**
     * Populate user profile by querying on his identifier.
     *
     * @return bool True if the user is retrieved
     */
    public function get()
    {
        if (is_int($this->id)) {
            require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
            $connection = new DatabaseConnection();
            $query = $connection->prepare('SELECT * FROM `user` WHERE `id`=:id LIMIT 1;');
            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
            if ($query->execute()) {
                $query->setFetchMode(PDO::FETCH_INTO, $this);
                //return true if there is user fetched, false otherwise
                return (bool) $query->fetch();
            }
            //return false to indicate an error occurred while reading the user
            return false;
        }
        //return false to indicate an error occurred while reading the user
        return false;
    }

    /**
     * Return if user has requested scope
     *
     * @param string $scope    Requested scope
     *
     * @return bool True if the user has the scope
     */
    public function hasScope($scope)
    {
        if (is_int($this->id)) {
            if (is_null($this->scope) || $this->scope === '') {
                //get scope from database
                require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
                $connection = new DatabaseConnection();
                $query = $connection->prepare('SELECT * FROM `user` WHERE `id`=:id LIMIT 1;');
                $query->bindValue(':id', $this->id, PDO::PARAM_INT);
                $query->setFetchMode(PDO::FETCH_INTO, $this);
                $query->execute();
                $query->fetch();
            }
            //return true if role is in scope
            return in_array($scope, explode(' ', $this->scope));
        }
        //return false to indicate an error occurred while reading the user scope
        return false;
    }

    /**
     * Check credentials and populate user if they are valid.
     *
     * @param string $login    User login
     * @param string $password User password
     *
     * @return bool True if the user credentials are valid, false otherwise
     */
    public function checkCredentials($login, $password)
    {
        $this->login = $login;
        $this->password = $password;
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT * FROM `user` WHERE `login`=:login AND `status`=1 LIMIT 1;');
        $query->bindValue(':login', $this->login, PDO::PARAM_STR);
        $query->setFetchMode(PDO::FETCH_INTO, $this);
        if ($query->execute() && $query->fetch()) {
            if ($this->loginAttemptFailed > 1 && $this->lastLoginAttemptFailed > (time() - 300)) {
                //return false if user is blocked (tried more than max attempts since 5 minutes)
                return false;
            }
            //return if password does match or not
            return password_verify($password, $this->password);
        }
        //return false to indicate an error occurred while reading the user
        return false;
    }

    /**
     * Store login attempt failed.
     *
     * @return bool True if the user login failure is updated
     */
    public function increaseLoginAttemptFailed()
    {
        if (!is_null($this->id)) {
            require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
            $connection = new DatabaseConnection();
            $query = $connection->prepare('UPDATE `user` SET `loginAttemptFailed`=`loginAttemptFailed`+1, `lastLoginAttemptFailed`=UNIX_TIMESTAMP() WHERE `id`=:id LIMIT 1;');
            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
            return $query->execute();
        }
        return false;
    }

    /**
     * Clear login attempt failed.
     *
     * @return bool True if the user login failure is updated
     */
    public function clearLoginAttemptFailed()
    {
        if (!is_null($this->id)) {
            require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
            $connection = new DatabaseConnection();
            $query = $connection->prepare('UPDATE `user` SET `loginAttemptFailed`=0, `lastLoginAttemptFailed`=null WHERE `id`=:id LIMIT 1;');
            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
            return $query->execute();
        }
        return false;
    }

    /**
     * Update user (login, scope and status).
     *
     * @param string $error The returned error message
     *
     * @return bool True if the user is updated
     */
    public function update(&$error)
    {
        $error = '';
        if (is_int($this->id)) {
            require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
            $connection = new DatabaseConnection();
            $query = $connection->prepare('UPDATE `user` SET `login`=:login, `scope`=:scope, `status`=:status, `mail`=:mail, `language`=:language WHERE `id`=:id;');
            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
            $query->bindValue(':login', $this->login, PDO::PARAM_STR);
            $query->bindValue(':scope', $this->scope, PDO::PARAM_STR);
            $query->bindValue(':status', $this->status, PDO::PARAM_BOOL);
            $query->bindValue(':mail', $this->mail, PDO::PARAM_STR);
            $query->bindValue(':language', $this->language, PDO::PARAM_STR);
            if ($query->execute()) {
                //return true to indicate a successful user update
                return true;
            }
            $error = ' '.$query->errorInfo()[2];
            //try to return intelligible error
            if ($query->errorInfo()[1] === 1062 || $query->errorInfo()[2] === 'UNIQUE constraint failed: user.login') {
                $error = ' : login `'.$this->login.'` already exists';
            }
        }
        //return false to indicate an error occurred while updating the user
        return false;
    }

    /**
     * Update user password.
     *
     * @param string $error The returned error message
     *
     * @return bool True if the user password is updated
     */
    public function updatePassword(&$error)
    {
        $error = '';
        if (is_int($this->id)) {
            require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
            $connection = new DatabaseConnection();
            $query = $connection->prepare('UPDATE `user` SET `password`=:password WHERE `id`=:id;');
            $query->bindValue(':id', $this->id, PDO::PARAM_INT);
            $query->bindValue(':password', password_hash($this->password, PASSWORD_DEFAULT), PDO::PARAM_STR);
            if ($query->execute()) {
                //return true to indicate a successful user update
                return true;
            }
            $error = ' '.$query->errorInfo()[2];
        }
        //return false to indicate an error occurred while updating the user
        return false;
    }

    /**
     * Return all user accounts.
     *
     * @return array User accounts
     */
    public function getAccounts()
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT * FROM `account` WHERE `user`=:user;');
        $query->bindValue(':user', $this->id, PDO::PARAM_STR);
        if ($query->execute()) {
            //return array of accounts
            return $query->fetchAll(PDO::FETCH_CLASS, 'Account');
        }
        //indicate there is a problem during querying
        return false;
    }

    /**
     * Return all user transactions.
     *
     * @param int $periodStart Start timestamp for requesting
     * @param int $periodEnd End timestamp for requesting
     *
     * @return array User transactions
     */
    public function getTransactions($periodStart = 0, $periodEnd = null)
    {
        if ($periodEnd === null) {
            $periodEnd = time();
        }
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT `transaction`.*, `account`.`hasIcon`, `account`.`label` AS `accountLabel` FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user` =:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd;');
        $query->bindValue(':user', $this->id, PDO::PARAM_STR);
        $query->bindValue(':periodStart', $periodStart, PDO::PARAM_INT);
        $query->bindValue(':periodEnd', $periodEnd, PDO::PARAM_INT);
        if ($query->execute()) {
            //return array of accounts
            return $query->fetchAll(PDO::FETCH_CLASS, 'Transaction');
        }
        //indicate there is a problem during querying
        return false;
    }

    /**
     * Return user balance.
     *
     * @param int $period Timestamp of the requested balance
     * @param int $budgetedOnly Handle only transaction from budgeted categories
     *
     * @return float Balance at requested period
     */
    public function getBalance($period = null, $budgetedOnly = true)
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT SUM(`balance`) AS `balance` FROM `account` WHERE `account`.`user` =:user;');
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        if (!$query->execute()) {
            return false;
        }
        $lastBalance = $query->fetch(PDO::FETCH_ASSOC);
        $balance = floatval($lastBalance['balance']);
        if ($period === null) {
            return $balance;
        }
        $query = $connection->prepare('SELECT SUM(`amount`) AS `balance` FROM `transaction` LEFT JOIN `category` ON `transaction`.`category`=`category`.`id`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user` =:user AND `datePosted` > :period AND (`isBudgeted` =:isBudgeted OR `isBudgeted`=TRUE OR `isBudgeted` IS NULL);');
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        $query->bindValue(':period', $period, PDO::PARAM_INT);
        $query->bindValue(':isBudgeted', $budgetedOnly, PDO::PARAM_BOOL);
        if (!$query->execute()) {
            return false;
        }
        $deltaBalance = $query->fetch(PDO::FETCH_ASSOC);
        $balance = $balance - floatval($deltaBalance['balance']);
        return $balance;
    }

    private function getTransactionsByType($type, $sqlFormat, $isRecurringOnly, $periodStart, $periodEnd, $budgetedOnly, $category, $subcategory)
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        if ($type === 'debit') {
            $countType = 'countDebit';
        } else {
            $countType = 'countCredit';
        }
        if ($category === null && $subcategory === null) {
            $query = $connection->prepare('SELECT FROM_UNIXTIME(`datePosted`, :format) AS `date`, SUM(`amount`) AS :type, COUNT(1) AS :countType FROM `transaction` LEFT JOIN `category` ON `transaction`.`category`=`category`.`id`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user`=:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd AND `type`=:type AND (`isBudgeted` =:isBudgeted OR `isBudgeted`=TRUE OR `isBudgeted` IS NULL) AND (`isRecurring`=:isRecurring OR `isRecurring`=TRUE) GROUP BY FROM_UNIXTIME(`datePosted`, :format) ORDER BY `datePosted` ASC;');
        } elseif ($subcategory === null) {
            //query only transactions in the specific category
            $query = $connection->prepare('SELECT FROM_UNIXTIME(`datePosted`, :format) AS `date`, SUM(`amount`) AS :type, COUNT(1) AS :countType FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user`=:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd AND `type`=:type AND `category`=:category AND (`isRecurring`=:isRecurring OR `isRecurring`=TRUE) GROUP BY FROM_UNIXTIME(`datePosted`, :format) ORDER BY `datePosted` ASC;');
            $query->bindValue(':category', $category, PDO::PARAM_INT);
        } else {
            //query only transactions in the specific subcategory
            $query = $connection->prepare('SELECT FROM_UNIXTIME(`datePosted`, :format) AS `date`, SUM(`amount`) AS :type, COUNT(1) AS :countType FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user`=:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd AND `type`=:type AND `subcategory`=:subcategory AND (`isRecurring`=:isRecurring OR `isRecurring`=TRUE) GROUP BY FROM_UNIXTIME(`datePosted`, :format) ORDER BY `datePosted` ASC;');
            $query->bindValue(':subcategory', $subcategory, PDO::PARAM_INT);
        }
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        $query->bindValue(':periodStart', $periodStart, PDO::PARAM_INT);
        $query->bindValue(':periodEnd', $periodEnd, PDO::PARAM_INT);
        $query->bindValue(':type', $type, PDO::PARAM_STR);
        $query->bindValue(':countType', $countType, PDO::PARAM_STR);
        $query->bindValue(':format', $sqlFormat, PDO::PARAM_STR);
        $query->bindValue(':isBudgeted', $budgetedOnly, PDO::PARAM_BOOL);
        $query->bindValue(':isRecurring', $isRecurringOnly, PDO::PARAM_BOOL);
        if (!$query->execute()) {
            return false;
        }
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return user transactions history.
     *
     * @param int $periodStart Start timestamp for requesting
     * @param int $periodEnd End timestamp for requesting
     * @param int $timeUnit Time unit (M for monthly, W for weekly, D for daily which is default value)
     * @param int $budgetedOnly Request only transaction from budgeted categories
     * @param int $category Category identifier
     * @param int $subcategory Subcategory identifier
     * @param boolean $isRecurringOnly Request only recurring transactions
     *
     * @return array User transactions history
     */
    public function getTransactionsHistory($periodStart, $periodEnd, $timeUnit = 'D', $budgetedOnly = true, $category = null, $subcategory = null, $isRecurringOnly = false)
    {
        switch ($timeUnit) {
          case 'M':
            $sqlFormat = '%Y-%m';
            $dateInterval = 'P1M';
            $resultFormat = 'Y-m';
            break;
          case 'W':
            $sqlFormat = '%xW%v';
            $dateInterval = 'P1W';
            $resultFormat = 'o\WW';
            break;
          case 'D':
          default:
            $sqlFormat = '%Y-%m-%d';
            $dateInterval = 'P1D';
            $resultFormat = 'Y-m-d';
            break;
        }

        //get debits
        if (!$isRecurringOnly) {
            $debits = $this->getTransactionsByType('debit', $sqlFormat, false, $periodStart, $periodEnd, $budgetedOnly, $category, $subcategory);
        }
        $debitsRecurring = $this->getTransactionsByType('debit', $sqlFormat, true, $periodStart, $periodEnd, $budgetedOnly, $category, $subcategory);

        //get credits
        if (!$isRecurringOnly) {
            $credits = $this->getTransactionsByType('credit', $sqlFormat, false, $periodStart, $periodEnd, $budgetedOnly, $category, $subcategory);
        }
        $creditsRecurring = $this->getTransactionsByType('credit', $sqlFormat, true, $periodStart, $periodEnd, $budgetedOnly, $category, $subcategory);

        //create calendar for returning each days
        $calendar = [];
        $dateTime = new DateTime();
        $dateTime->setTimestamp($periodStart);
        do {
            $date = $dateTime->format($resultFormat);
            $calendar[$date]['debit'] = 0;
            $calendar[$date]['credit'] = 0;
            $calendar[$date]['countCredit'] = 0;
            $calendar[$date]['countDebit'] = 0;
            $dateTime->add(new DateInterval($dateInterval));
        } while ($dateTime->getTimestamp() <= $periodEnd);

        //put amount in calendar
        if (!$isRecurringOnly) {
            foreach ($debits as $point) {
                $calendar[$point['date']]['debit'] = floatval($point['debit']);
                $calendar[$point['date']]['countDebit'] = intval($point['countDebit']);
            }
            foreach ($credits as $point) {
                $calendar[$point['date']]['credit'] = floatval($point['credit']);
                $calendar[$point['date']]['countCredit'] = intval($point['countCredit']);
            }
        }
        foreach ($debitsRecurring as $point) {
            $calendar[$point['date']]['debitRecurring'] = floatval($point['debit']);
            $calendar[$point['date']]['countDebitRecurring'] = intval($point['countDebit']);
        }
        foreach ($creditsRecurring as $point) {
            $calendar[$point['date']]['creditRecurring'] = floatval($point['credit']);
            $calendar[$point['date']]['countCreditRecurring'] = intval($point['countCredit']);
        }

        //get balances (get balance at end period, then remove transactions sum for each date)
        if ($category === null && $subcategory === null) {
            $balance = $this->getBalance($periodEnd, $budgetedOnly);
            $calendar = array_reverse($calendar);
            foreach ($calendar as $date => $value) {
                $calendar[$date]['balance'] = $balance;
                $balance = $balance - $value['debit'] - $value['credit'];
            }
            $calendar = array_reverse($calendar);
        }

        //format in array
        $points = [];
        foreach ($calendar as $date => $value) {
            $point = new stdClass();
            $point->date = $date;
            if (!$isRecurringOnly) {
                $point->debit = key_exists('debit', $value) ? $value['debit'] : 0;
                $point->credit = key_exists('credit', $value) ? $value['credit'] : 0;
                $point->countDebit = key_exists('countDebit', $value) ? $value['countDebit'] : 0;
                $point->countCredit = key_exists('countCredit', $value) ? $value['countCredit'] : 0;
            }
            $point->debitRecurring = key_exists('debitRecurring', $value) ? $value['debitRecurring'] : 0;
            $point->creditRecurring = key_exists('creditRecurring', $value) ? $value['creditRecurring'] : 0;
            $point->countDebitRecurring = key_exists('countDebitRecurring', $value) ? $value['countDebitRecurring'] : 0;
            $point->countCreditRecurring = key_exists('countCreditRecurring', $value) ? $value['countCreditRecurring'] : 0;
            if ($category === null && $subcategory === null) {
                $point->balance = $value['balance'];
            }
            array_push($points, $point);
        }
        //order by date
        function dateCompare($a, $b)
        {
            return strcmp($a->date, $b->date);
        }
        usort($points, 'dateCompare');

        return $points;
    }

    /**
     * Return user transactions distribution.
     *
     * @param int $periodStart Start timestamp for requesting
     * @param int $periodEnd End timestamp for requesting
     * @param string $type Transaction type ('credit' or 'debit')
     * @param string $key Distribution group by field ('category', 'subcategory')
     * @param string|int $value Value for distribution on a specific field (category.id for subcategory distribution)
     * @param boolean $budgetedOnly Request only budgeted category
     * @param boolean $isRecurringOnly Request only recurring transactions
     *
     * @return array User transactions distribution
     */
    public function getTransactionsDistribution($periodStart, $periodEnd, $type, $key, $value = null, $budgetedOnly = true, $isRecurringOnly = false)
    {
        if ($key === 'categories') {
            $queryString = 'SELECT `category` AS `key`, SUM(`amount`) AS `amount` FROM `transaction` LEFT JOIN `category` ON `transaction`.`category`=`category`.`id`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user`=:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd AND `type`=:type AND (`isBudgeted` =:isBudgeted OR `isBudgeted`=TRUE OR `isBudgeted` IS NULL) AND (`isRecurring`=:isRecurring OR `isRecurring`=TRUE) GROUP BY `category` ORDER BY SUM(ABS(`amount`)) DESC;';
        } elseif ($key === 'subcategories' && $value !== null) {
            $queryString = 'SELECT `subcategory` AS `key`, SUM(`amount`) AS `amount` FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user`=:user AND `datePosted` > :periodStart AND `datePosted` < :periodEnd AND `category`=:category AND `type`=:type AND (`isRecurring`=:isRecurring OR `isRecurring`=TRUE) GROUP BY `subcategory` ORDER BY SUM(ABS(`amount`)) DESC;';
        } else {
            //invalid request
            return false;
        }
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare($queryString);
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        $query->bindValue(':periodStart', $periodStart, PDO::PARAM_INT);
        $query->bindValue(':periodEnd', $periodEnd, PDO::PARAM_INT);
        if ($key === 'subcategories') {
            $query->bindValue(':category', $value, PDO::PARAM_INT);
        } else {
            $query->bindValue(':isBudgeted', $budgetedOnly, PDO::PARAM_BOOL);
        }
        $query->bindValue(':type', $type, PDO::PARAM_STR);
        $query->bindValue(':isRecurring', $isRecurringOnly, PDO::PARAM_BOOL);
        if (!$query->execute()) {
            return false;
        }
        $distribution = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($distribution as &$item) {
            if ($item['key'] !== null) {
                $item['key'] = intval($item['key']);
            }
            $item['amount'] = intval($item['amount']);
        }
        return $distribution;
    }

    /**
     * Return user transactions matching provided pattern
     *
     * @param string $pattern Pattern label to search
     * @param string $error The returned error message
     *
     * @return array User transactions matching pattern
     */
    public function getTransactionsByPattern($pattern, &$error)
    {
        $error = '';
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Transaction.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT `transaction`.* FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user` =:user AND CONCAT(`memo`, " ", `name`) like :pattern;');
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        $query->bindValue(':pattern', $pattern, PDO::PARAM_STR);
        if ($query->execute()) {
            //return array of transactions
            return $query->fetchAll(PDO::FETCH_CLASS, 'Transaction');
        }
        $error = $query->errorInfo()[2];
        //indicate there is a problem during querying
        return false;
    }

    /**
     * Return suggested patterns from user's transactions
     *
     * @return array Suggested patterns
     */
    public function suggestPatterns()
    {
        //set memory_limit from configuration
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Configuration.php';
        $configuration = new Configuration();
        $memory_limit = $configuration->get('patternsMemoryLimit');
        if (! $memory_limit) {
            $memory_limit = '256M';
        }
        ini_set('memory_limit', $memory_limit);

        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Pattern.php';
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Transaction.php';
        //get user transactions
        $transactions = $this->getTransactions();

        //create raw patterns
        $minLength = 7;
        $rawPatterns = [];
        foreach ($transactions as $transaction) {
            $transactionLabel = $transaction->name;
            if ($transaction->memo) {
                $transactionLabel = $transaction->memo . ' ' . $transaction->name;
            }
            $transactionLabelLength = strlen($transactionLabel);
            if ($transactionLabelLength > $minLength) {
                for ($i = 0; $i < $transactionLabelLength - $minLength; $i++) {
                    for ($j = $minLength; $j <= $transactionLabelLength - $i; $j++) {
                        $patternText = substr($transactionLabel, $i, $j);
                        array_push($rawPatterns, $patternText);
                    }
                }
            }
        }

        //remove insufficient patterns (less than 5 occurences) and empty ones
        $rawPatterns = array_count_values($rawPatterns);
        $rawPatterns = array_filter($rawPatterns, function ($v, $k) {
            return $v > 4 && strlen(trim($k)) > 0 ;
        }, ARRAY_FILTER_USE_BOTH);

        //remove smaller patterns (included in longer one)
        uksort($rawPatterns, function ($a, $b) {
            return strlen($b)-strlen($a);
        });
        $rawPatterns = array_keys($rawPatterns);
        $longestPatterns = [];
        foreach ($rawPatterns as $patternText) {
            $isIncluded = false;
            foreach ($longestPatterns as $longestPattern) {
                if (strpos($longestPattern, $patternText) !== false) {
                    $isIncluded = true;
                }
            }
            if (!$isIncluded) {
                array_push($longestPatterns, $patternText);
            }
        }
        $rawPatterns = $longestPatterns;
        unset($longestPatterns);

        //compare each transaction to keep patterns with only one category
        $validPatterns = [];
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/Transaction.php';
        $connection = new DatabaseConnection();
        foreach ($rawPatterns as $patternText) {
            //get matching transactions with category
            $query = $connection->prepare('SELECT `transaction`.`category`, `transaction`.`subcategory`, `transaction`.`isRecurring`, count(1) AS `count` FROM `transaction`, `account` WHERE `account`.`id`=`transaction`.`aid` AND `account`.`user` =:user AND `transaction`.`category` IS NOT NULL AND CONCAT(`memo`, " ", `name`) like :pattern GROUP BY `transaction`.`category`, `transaction`.`subcategory`, `transaction`.`isRecurring`;');
            $query->bindValue(':user', $this->id, PDO::PARAM_INT);
            $query->bindValue(':pattern', "%$patternText%", PDO::PARAM_STR);
            $query->execute();
            $transactions = $query->fetchAll(PDO::FETCH_CLASS, 'Transaction');
            //check all transactions have same category / subcategory
            if (count($transactions) === 1) {
                $pattern = new Pattern();
                $pattern->label = "*$patternText*";
                $pattern->category = $transactions[0]->category;
                $pattern->subcategory = $transactions[0]->subcategory;
                $pattern->isRecurring = $transactions[0]->isRecurring;
                $pattern->count = $transactions[0]->count;
                array_push($validPatterns, $pattern);
            }
        }
        ini_restore('memory_limit');

        //return the valided patterns
        return $validPatterns;
    }

    /**
     * Return public profile.
     *
     * @return object A public version of user profile
     */
    public function getProfile()
    {
        $user = new stdClass();
        $user->sub = (int) $this->id;
        $user->login = $this->login;
        $user->status = (bool) $this->status;
        $user->mail = $this->mail;
        $user->language = $this->language;
        //get user scope as a list of space-delimited strings (see https://tools.ietf.org/html/rfc6749#section-3.3)
        $user->scope = $this->scope;
        //returns the user public profile
        return $user;
    }

    /**
     * Return user last connections.
     *
     * @return array Last connections (date, ip)
     */
    public function getLastConnections()
    {
        require_once $_SERVER['DOCUMENT_ROOT'].'/server/lib/DatabaseConnection.php';
        $connection = new DatabaseConnection();
        $query = $connection->prepare('SELECT * FROM `token` WHERE `user` =:user;');
        $query->bindValue(':user', $this->id, PDO::PARAM_INT);
        if ($query->execute()) {
            //return array of connections
            return $query->fetchAll(PDO::FETCH_OBJ);
        }
        //indicate there is a problem during querying
        return false;
    }
}
