<?php

namespace soladiem\autoMinify\components;

use yii\helpers\ArrayHelper;

/**
 * Class HtmlCompressor
 * @package sdy\autoMinify\components
 */
class HtmlCompressor
{
    /**
     * @param string $data is either a handle to an open file, or an HTML string
     * @param null|array $options key => value array of execute options
     * The possible keys are:
     *
     *  - `c` or `no-comments` - removes HTML comments
     *  - `s` or `stats` - output filesize savings calculation
     *  - `x` or `extra` - perform extra (possibly unsafe) compression operations
     *
     * Example: HtmlCompressor::compress($HtmlCode, $options = ['no-comments' => true])
     *
     * @return string
     */
    public static function compress($data, $options = null)
    {
        return (new static)->htmlCompress($data, $options);
    }
    /**
     * HTML Compressor 1.0.1
     * Original Author: Tyler Hall <tylerhall@gmail.com>
     * Edited by: Revin Roman <xgismox@gmail.com>
     * Latest Source and Bug Tracker: http://github.com/tylerhall/html-compressor
     *
     * Attemps to reduce the filesize of an HTML document by removing unnecessary
     * whitespace at the beginning and end of lines, inside closing tags, and
     * stripping blank lines completely. <pre> tags are respected and their contents
     * are left alone. Warning, nested <pre> tags may exhibit unexpected behaviour.
     *
     * This code is licensed under the MIT Open Source License.
     * Copyright (c) 2010 tylerhall@gmail.com
     * Permission is hereby granted, free of charge, to any person obtaining a copy
     * of this software and associated documentation files (the "Software"), to deal
     * in the Software without restriction, including without limitation the rights
     * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
     * copies of the Software, and to permit persons to whom the Software is
     * furnished to do so, subject to the following conditions:
     *
     * The above copyright notice and this permission notice shall be included in
     * all copies or substantial portions of the Software.
     *
     * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
     * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
     * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
     * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
     * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
     * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
     * THE SOFTWARE.
     *
     * @param $data
     * @param null|array $options
     * @return bool|mixed|string
     */
    private function htmlCompress($data, $options = null)
    {
        if (!isset($options)) {
            $options = [];
        }
        $data .= "\n";
        $out = '';
        $insidePre = false;
        $insideTextarea = false;
        $bytecount = 0;
        while ($line = $this->getLine($data)) {
            $bytecount += strlen($line);
            if ($insidePre) {
                list($line, $insidePre) = $this->checkInsidePre($line);
            } elseif ($insideTextarea) {
                list($line, $insideTextarea) = $this->checkInsideTextarea($line);
            } else {
                if (strpos($line, '<pre') !== false) {
                    // Only trim the beginning since we just entered a <pre> block...
                    $line = ltrim($line);
                    // If the <pre> ends on the same line, don't turn on $insidePre...
                    list($line, $insidePre) = $this->checkInsidePre($line);
                } elseif (strpos($line, '<textarea') !== false) {
                    // Only trim the beginning since we just entered a <textarea> block...
                    $line = ltrim($line);
                    // If the <textarea> ends on the same line, don't turn on $insideTextarea...
                    list($line, $insideTextarea) = $this->checkInsideTextarea($line);
                } else {
                    // Since we're not inside a <pre> block, we can trim both ends of the line
                    $line = trim($line);
                    // And condense multiple spaces down to one
                    $line = preg_replace('/\s\s+/', ' ', $line);
                }
            }
            // Filter out any blank lines that aren't inside a <pre> block...
            if ($insidePre || $insideTextarea) {
                $out .= $line;
            } elseif ($line != '') {
                $out .= $line . "\n";
            }
        }
        // Perform any extra (unsafe) compression techniques...
        if (array_key_exists('x', $options) || ArrayHelper::getValue($options, 'extra') === true) {
            // Can break layouts that are dependent on whitespace between tags
            $out = str_replace(">\n<", '><', $out);
        }
        // Remove HTML comments...
        if (array_key_exists('c', $options) || ArrayHelper::getValue($options, 'no-comments') === true) {
            $out = preg_replace('/(<!--.*?-->)/ms', '', $out);
            $out = str_replace('<!>', '', $out);
        }
        // Remove the trailing \n
        $out = trim($out);
        // Output either our stats or the compressed data...
        if (array_key_exists('s', $options) || ArrayHelper::getValue($options, 'stats') === true) {
            $echo = '';
            $echo .= "Original Size: $bytecount\n";
            $echo .= "Compressed Size: " . strlen($out) . "\n";
            $echo .= "Savings: " . round((1 - strlen($out) / $bytecount) * 100, 2) . "%\n";
            echo $echo;
        } else {
            return $out;
        }
        return false;
    }
    /**
     * @param $line
     * @return array
     */
    private function checkInsidePre($line)
    {
        $insidePre = true;
        if ((strpos($line, '</pre') !== false) && (strripos($line, '</pre') >= strripos($line, '<pre'))) {
            $line = rtrim($line);
            $insidePre = false;
        }
        return [$line, $insidePre];
    }
    /**
     * @param $line
     * @return array
     */
    private function checkInsideTextarea($line)
    {
        $insideTextarea = true;
        if ((strpos($line, '</textarea') !== false) && (strripos($line, '</textarea') >= strripos($line, '<textarea'))) {
            $line = rtrim($line);
            $insideTextarea = false;
        }
        return [$line, $insideTextarea];
    }
    /**
     * Returns the next line from an open file handle or a string
     * @param $data
     * @return bool|string
     */
    private function getLine(&$data)
    {
        if (is_resource($data)) {
            return fgets($data);
        }
        if (is_string($data)) {
            if (strlen($data) > 0) {
                $pos = strpos($data, "\n");
                $return = substr($data, 0, $pos) . "\n";
                $data = substr($data, $pos + 1);
                return $return;
            } else {
                return false;
            }
        }
        return false;
    }
}
