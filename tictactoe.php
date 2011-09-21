<?php

class tictactoe
{
    /**
     * Size of grid
     *
     * @var int
     */
    const GRID_SIZE = 3;

    const EMPTY_VALUE = 0;

    const HUMAN_MARKER = 'O';
    const HUMAN_VALUE = 1;

    const MACHINE_MARKER = 'X';
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

    public function getMessage()
    {
        if ($this->_stale) {
            return 'Stale game.';
        }

        return empty($this->_message) ? 'Your turn...' : $this->_message;
    }

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

    protected function _getCellClass($row, $col)
    {
        if (isset($this->_winner[$row][$col]) && $this->_winner[$row][$col]) {
            return 'winner';
        }
    }

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
                array(0, 0), // Top left
                array(2, 2), // Bottom right
            ),
            array(
                array(0, 2), // Top right
                array(2, 0), // Bottom left
            ),
            array(
                array(2, 0), // Bottom left
                array(0, 2), // Top right
            ),
            array(
                array(2, 2), // Bottom right
                array(0, 0), // Top left
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

$tictactoe = new tictactoe();
$status = $tictactoe->getMessage();
$round = $tictactoe->getRound();
?>
<html>
  <head>
    <title>Tic-Tac-Toe</title>
    <style>
        table {
            font-size: bigger;
            width: 10em;
            heigth: 10em;
            border: 1px solid black;
        }
        .status {
            background-color: lightyellow;
        }
        .winner {
            background-color: green;
        }
    </style>
  </head>
  <body>
    <h1>Tic-Tac-Toe (<?php echo htmlentities($round); ?>)</h1>
    <div id="tictactoe">
      <form method="post">
        <?php echo $tictactoe->render(); ?>
      </form>
    </div>
    <div class="status">
        <?php echo $status; ?>
        <br />
        <a href="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>">Restart game</a>
    </div>
  </body>
</html>