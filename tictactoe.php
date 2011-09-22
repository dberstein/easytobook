<?php

/**
 * view
 */
class view
{
    /**
     * Singleton instance
     *
     * @var self
     */
    static protected $_instance;

    /**
     * Temporary storage for content
     *
     * @var array|null
     */
    protected $_html;

    /**
     * Constructor
     *
     * @return self
     */
    protected function __construct()
    {
        $this->_html = array(
            'title' => null,
            'style' => null,
            'body' => null,
        );
    }

    /**
     * Return singleton instance
     *
     * @return self
     */
    static public function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    /**
     * Returns string representation of view's content into a hardcoded HTML
     * layout
     *
     * @return string
     */
    public function __toString()
    {
        $title = $this->_html['title'];
        $body = $this->_html['body'];
        $style = $this->_html['style'];

        return <<<EOT
<html>
  <head>
    <title>{$title}</title>
    <style>{$style}</style>
  </head>
  <body>
{$body}
  </body>
</html>
EOT;
    }

    /**
     * Catches calls to methods of the form "addXXX" and redirects them to
     * protected method "_setXXX".
     *
     * @return string
     */
    public function __call($name, $args)
    {
        if (preg_match('/^add([A-Z].+)$/', $name, $matches)) {
            $cb = array($this, '_set' . $matches[1]);
            if (is_callable($cb)) {
                return call_user_func_array($cb, $args);
            }
        }
    }

    /**
     * Sets the layout's HTML title
     *
     * @param string $data
     *
     * @return void
     */
    protected function _setTitle($data)
    {
        $this->_html['title'] = $data;
    }

    /**
     * Sets the layout's HTML inline CSS style
     *
     * @param string $data
     *
     * @return void
     */
    protected function _setStyle($data)
    {
        $this->_html['style'] = $data;
    }

    /**
     * Sets the layout's HTML body
     *
     * @param string $data
     *
     * @return void
     */
    protected function _setBody($data)
    {
        $this->_html['body'] = $data;
    }

    /**
     * Appends to the layout's HTML body a form element
     *
     * @param string $uri    The action URI for the form
     * @param string $method The method for the form
     * @param string $body   The innerHTML of the form
     *
     * @return void
     */
    protected function _setForm($uri, $method, $body)
    {
        $this->_html['body'] .= sprintf(
            '<form action="%s" method="%s">%s</form>',
            htmlentities($uri),
            htmlentities($method),
            $body
        );
    }

    /**
     * Appends to the layout's HTML body a div element
     *
     * @param string $class  CSS class for the div
     * @param string $body   The innerHTML of the div
     *
     * @return void
     */
    protected function _setDiv($class, $body)
    {
        $this->_html['body'] .= sprintf(
            '<div class="%s">%s</div>',
            htmlentities($class),
            $body
        );
    }
}

/**
 * model
 */
class model
{
    /**
     * Size of grid
     *
     * @var int
     */
    const GRID_SIZE = 3;

    /**
     * Value that represent empty
     *
     * @var int
     */
    const EMPTY_VALUE = 0;

    /**
     * String for displaying human's cells
     *
     * @var string
     */
    const HUMAN_MARKER = 'O';

    /**
     * Value that represent a cell selected by the human
     *
     * @var int
     */
    const HUMAN_VALUE = 1;

    /**
     * String for displaying machine's cells
     *
     * @var string
     */
    const MACHINE_MARKER = 'X';

    /**
    * Value that represent a cell selected by the machiune
     *
     * @var int
     */
    const MACHINE_VALUE = -1;

    /**
     * Pre-computed winning positions
     *
     * @var array
     */
    protected $_solutions = array(
        'row 1' => array(
            array(1,1,1),
            array(0,0,0),
            array(0,0,0),
        ),
        'row 2' => array(
            array(0,0,0),
            array(1,1,1),
            array(0,0,0),
        ),
        'row 3' => array(
            array(0,0,0),
            array(0,0,0),
            array(1,1,1),
        ),
        'column 1' => array(
            array(1,0,0),
            array(1,0,0),
            array(1,0,0),
        ),
        'column 2' => array(
            array(0,1,0),
            array(0,1,0),
            array(0,1,0),
        ),
        'column 3' => array(
            array(0,0,1),
            array(0,0,1),
            array(0,0,1),
        ),
        'top left to bottom right' => array(
            array(1,0,0),
            array(0,1,0),
            array(0,0,1),
        ),
        'top right to bottom left' => array(
            array(0,0,1),
            array(0,1,0),
            array(1,0,0),
        ),
    );

    /**
     * Array of winning solution.
     *
     * @var array
     */
    protected $_winner = array();

    /**
     * Representation of playing field.
     *
     * @var array
     */
    protected $_game = array();

    /**
     * Storage for messages for user
     *
     * @var string
     */
    protected $_message;

    /**
     * Stale game
     *
     * @var bool
     */
    protected $_finished = false;

    /**
     * Flag to indicate a stale game
     *
     * @var bool
     */
    protected $_stale = false;

    /**
     * Wether board is closed (ie. read-oonly or not).
     *
     * @var bool
     */
    protected $_closed = false;

    /**
     * Form element name that holds the board.
     *
     * @var string
     */
    protected $_fieldName;

    /**
     * Constructor
     *
     * @parm $fieldName Name of the HTML form field for the board
     *
     * @return self
     */
    public function __construct($fieldName = 'game')
    {
        // Fetch/build playing grid
        $this->_fieldName = $fieldName;
        $game = array();
        if (0 === strcasecmp('POST', $_SERVER['REQUEST_METHOD'])) {
            $game = $_POST[$this->_fieldName];
        }

        $this->_game = $this->_build($game);

        // Has the user won?
        $winner = $this->isWinner(self::HUMAN_VALUE);

        if ($winner) {
            $this->_message = '<b>Human</b> won, completed: <b>' . $winner . '</b>';
        } else {
            // Machine's move
            $this->play();

            // Has the machine won?
            $winner = $this->isWinner(self::MACHINE_VALUE);
            if ($winner) {
                $this->_message = '<b>Machine</b> won, completed: <b>' . $winner . '</b>';
            }
        }

        // Was there a winner?
        if ($winner) {
            $this->_closed = true;
        } else {
            if ($this->_stale) {
                // Stale game
                $this->_message = 'Stale game!';
                $this->_closed = true;
            } else {
                $this->_message = 'Your turn ...';
            }
        }

    }

    /**
     * Returns matched solution's key or false.
     *
     * @param int   $filter
     *
     * @return string|false
     */
    public function isWinner($filter)
    {
        $normalized = array();

        // Normalize game according to filter
        for ($i = 0; $i < self::GRID_SIZE; $i++) {
            $normalized[$i] = array();

            for ($j = 0; $j < self::GRID_SIZE; $j++) {
                if (!isset($this->_game[$i][$j]) || $filter != $this->_game[$i][$j]) {
                    $normalized[$i][$j] = self::EMPTY_VALUE;
                } else {
                    $normalized[$i][$j] = $this->_game[$i][$j];
                }

                $normalized[$i][$j] *= $filter;
            }
        }

        // Search for winning match
        foreach ($this->_solutions as $key => $winner) {
            // Map solution into normalized data
            $test = array();
            for ($i = 0; $i < self::GRID_SIZE; $i++) {
                for ($j = 0; $j < self::GRID_SIZE; $j++) {
                    $test[$i][$j] = ($winner[$i][$j] && $normalized[$i][$j]) ? $winner[$i][$j] : self::EMPTY_VALUE;
                }
            }

            if ($test == $winner) {
                // We have a winner!
                $this->_stale = false;
                $this->_winner = $winner;

                return $key;
            }
        }

        return false;
    }

    /**
     * Returns normalized array that represents the board.
     *
     * @param array $game
     *
     * @return array
     */
    protected function _build(array $game = array())
    {
        for ($i = 0; $i < self::GRID_SIZE; $i++) {
            for ($j = 0; $j < self::GRID_SIZE; $j++) {
                $game[$i][$j] = isset($game[$i][$j]) ? (int) $game[$i][$j] : self::EMPTY_VALUE;
            }

            ksort($game[$i]);
        }

        return $game;
    }

    /**
     * Returns string message to display to user.
     *
     * @return string
     */
    public function getMessage()
    {
        if ($this->_stale) {
            return 'Stale game.';
        }

        return empty($this->_message) ? 'Your turn...' : $this->_message;
    }

    /**
     * Returns HTML string representation of the board.
     *
     * @return string
     */
    public function render()
    {
        $html = null;
        for ($i = 0; $i < self::GRID_SIZE; $i++) {
            $html .= '<tr>';

            for ($j = 0; $j < self::GRID_SIZE; $j++) {
                $value = $this->_game[$i][$j];
                switch ($value) {
                    case self::HUMAN_VALUE:
                        $cell = $this->_cellHtml(
                            $i,
                            $j,
                            self::HUMAN_MARKER,
                            $value
                        );

                        break;
                    case self::MACHINE_VALUE:
                        $cell = $this->_cellHtml(
                            $i,
                            $j,
                            self::MACHINE_MARKER,
                            $value
                        );

                        break;
                    default:
                        $cell = sprintf(
                            '<input type="checkbox" name="%s[%d][%d]" value="%d" onclick="this.form.submit()" %s/>',
                            $this->_fieldName,
                            $i,
                            $j,
                            self::HUMAN_VALUE,
                            ($this->_closed) ? 'disabled="true"' : null
                        );

                        break;
                }

                $html .= sprintf(
                    '<td class="cell %s">%s</td>',
                    $this->_getCellClass($i, $j),
                    $cell
                );
            }

            $html .= '</tr>';
        }

        return '<table>' . $html . '</table>';
    }

    /**
     * Returns CSS class name to use when rendering a cell.
     *
     * @return string
     */
    protected function _getCellClass($row, $col)
    {
        $class = null;

        // Is this cell part of the winning solution?
        if (isset($this->_winner[$row][$col]) && $this->_winner[$row][$col]) {
            $class = 'winner';
        }

        return $class;
    }

    /**
     * Returns HTML to represent a board's cell
     *
     * @param int    $row    Cell row number
     * @param int    $col    Cell column number
     * @param string $marker String to mark the cell
     * @param int    $value  Cell's value
     *
     * @return string
     */
    protected function _cellHtml($row, $col, $marker, $value)
    {
        return sprintf(
            '<b>%s</b><input type="hidden" name="%s[%d][%d]" value="%d" />',
            htmlentities($marker),
            $this->_fieldName,
            $row,
            $col,
            (int) $value
        );
    }

    /**
     * Returns play round number as plain integer.
     *
     * @return int
     */
    protected function _round()
    {
        // Calculate which play round is this by counting how many
        // {@see self::MACHINE_VALUE} we have.
        $round = 1;
        for ($i = 0; $i < self::GRID_SIZE; $i++) {
            $moves = array_keys(
                $this->_game[$i],
                self::MACHINE_VALUE
            );

            $round += count($moves);
        }

        if ($round > 4) {
            $this->_stale = true;
        }

        return $round;
    }

    /**
     * Returns play round number as decorated string.
     *
     * @return string
     */
    public function getRound()
    {
        $round = $this->_round();
        if ($round >= 4) {
            $this->_stale = true;
        }

        return sprintf(
            'round #%d',
            $round - 1
        );
    }

    /**
     * Places machine's move into the play's grid.
     *
     * Follows a basic strategy:
     *    1. 1st move is always for the center of the grid.
     *    2. 2nd move is for a free corner.
     *    3. 3rd tries to close game by finishing diagonal.
     *    4. If diagonal not possible then force stalement by blocking
     *       human's actions.
     *
     * @return void
     */
    public function play()
    {
        // Grid's opposed corners
        $corners = array(
            array(
                array(0, 2), // Top right
                array(2, 0), // Bottom left
            ),
            array(
                array(2, 2), // Bottom right
                array(0, 0), // Top left
            ),
            array(
                array(0, 0), // Top left
                array(2, 2), // Bottom right
            ),
            array(
                array(2, 0), // Bottom left
                array(0, 2), // Top right
            ),
        );

        // Action depends on current round
        $round = $this->_round();
        switch ($round) {
            case 1:
                // 1st round is always at center of grid
                $center = (int) (self::GRID_SIZE/2);
                $this->_game[$center][$center] = self::MACHINE_VALUE;
                break;
            case 2:
                // 2nd round is always a free corner
                foreach ($corners as $c) {
                    if ($this->_game[$c[0][0]][$c[0][1]] == 0) {
                        if ($this->_game[$c[1][0]][$c[1][1]] == 0) {
                            $this->_game[$c[0][0]][$c[0][1]] = self::MACHINE_VALUE;
                            break 2;
                        }
                    }
                }
                break;
            default:
                // Other rounds try to close diagonal
                foreach ($corners as $c) {
                    if (
                        // Owns the corner ...
                        ($this->_game[$c[0][0]][$c[0][1]] == self::MACHINE_VALUE)
                        // And opossite corner is available
                        && ($this->_game[$c[1][0]][$c[1][1]] == self::EMPTY_VALUE)
                    ) {
                        // Go for the kill!
                        $this->_game[$c[1][0]][$c[1][1]] = self::MACHINE_VALUE;
                        $this->_finished = true;
                        break;
                    }
                }

                // If didn't close diagonal, just block human from winning
                if (!$this->_finished) {
                    // Block human completing row
                    $blocked = false;
                    for ($i = 0; $i < self::GRID_SIZE; $i++) {
                        $moves = array_keys(
                            $this->_game[$i],
                            self::HUMAN_VALUE
                        );

                        if (self::GRID_SIZE - 1 == count($moves)) {
                            for ($j = 0; $j < self::GRID_SIZE; $j++) {
                                if ($this->_game[$i][$j] == self::EMPTY_VALUE) {
                                    $this->_game[$i][$j] = self::MACHINE_VALUE;
                                    $blocked = true;
                                    break;
                                }
                            }
                        }
                    }

                    // Block human completing column
                    if (!$blocked) {
                        for ($j = 0; $j < self::GRID_SIZE; $j++) {
                            for ($i = 0; $i < self::GRID_SIZE; $i++) {
                                if (
                                    isset($this->_game[$i][$j])
                                    && $this->_game[$i][$j] == self::EMPTY_VALUE
                                ) {
                                    $this->_game[$i][$j] = self::MACHINE_VALUE;
                                    break 2;
                                }
                            }
                        }
                    }
                }
        }
    }
}

/**
 * controller
 */
class controller
{
    /**
     * View instance
     *
     * @var view
     */
    protected $_view;

    /**
     * Constructor
     *
     * @return self
     */
    public function __construct()
    {
        // Initialize view instance
        $this->_view = view::getInstance();
    }

    /**
     * Dispatches request to proper action and returns generated HTML.
     *
     * @return string
     */
    public function dispatch()
    {
        // All request handled by "play" action
        $action = 'play';

        // Invoque action
        return call_user_func(
            array(
                $this,
                $action
            )
        );
    }

    /**
     * Default action
     *
     * @return void
     */
    public function play()
    {
        // Instantiate model (ie. business logic class)
        $model = new model;
        $status = $model->getMessage();
        $round = $model->getRound();

        // Construct HTML response
        $this->_view->addTitle(
            'Tic-Tac-Toe'
        );

        // Add CSS styles
        $this->_view->addStyle(
    <<<EOT
table {
    font-size: bigger;
    width: 10em;
    heigth: 10em;
    border: 1px solid black;
}
.status {
    background-color: lightyellow;
}
.restart {
    background-color: silver;
}
.winner {
    background-color: green;
}
EOT
        );

        // Add board in a form
        $this->_view->addForm(
            null,
            'post',
            $model->render()
        );

        // Add status/messages div
        $this->_view->addDiv(
            'status',
            $status
        );

        // Add restart div
        $this->_view->addDiv(
            'restart',
            '<a href="' . htmlentities($_SERVER['PHP_SELF']) . '">Restart</a>'
        );

        // Return "stringified" view
        return (string) $this->_view;
    }
}

// Instantiate and output dispatched action request response
$controller = new controller;
echo $controller->dispatch();