<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Traits;

/**
 * Short methods to add default conditions to query.
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
trait FilterTrait
{
    public function conditions(&$data = [], $alias = null)
    {
        $conditions = [];

        if ($alias) {
            $alias = $alias.'.';
        }

        $filter = $data['dfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($data[$k] != '') {
                    $conditions[] = [$alias.$this->cleanField($v).' like ' => '%'.$data[$k].'%'];
                }
            }
        }

        $filter = $data['filter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$this->cleanField($k) => $v];
                }
            }
        }

        $filter = $data['lfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$this->cleanField($k).' like ' => '%'.$v.'%'];
                }
            }
        }

        $filter = $data['rfilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$this->cleanField($k).' like ' => $v.'%'];
                }
            }
        }

        $filter = $data['efilter'];
        if (is_array($filter)) {
            foreach ($filter as $k => $v) {
                if ($v[0] != '') {
                    $conditions[] = [$alias.$this->cleanField($k).' like ' => '%'.$v];
                }
            }
        }

        $date_range = $data['date_range'];
        if (is_array($date_range)) {
            foreach ($date_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = ['DATE('.$alias.$this->cleanField($k).') BETWEEN ? AND ? ' => [
                        $this->toTime($from, true),
                        $this->toTime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE('.$alias.$this->cleanField($k).')' => $this->toTime($from, true)];
                }
            }
        }

        $time_range = $data['time_range'];
        if (is_array($time_range)) {
            foreach ($time_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$this->cleanField($k).')) BETWEEN ? AND ? ' => [
                        $this->toTime($from, true),
                        $this->toTime($to, true),
                        ],
                    ];
                }

                if ($from && empty($to)) {
                    $conditions[] = ['DATE(FROM_UNIXTIME('.$alias.$this->cleanField($k).'))' => $this->toTime($from, true)];
                }
            }
        }

        $date_range = $data['unix_range'];
        if (is_array($date_range)) {
            foreach ($date_range as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && empty($to)) {
                    $to = $from;
                }

                if (!is_numeric($from)) {
                    $from = $this->toTime($from.' 00:00:00');
                }

                if (!is_numeric($to)) {
                    $to = $this->toTime($to.' 23:59:59');
                }

                $conditions[] = [($alias.$this->cleanField($k)).' BETWEEN ? AND ? ' => [$from, $to]];
            }
        }

        $ranges = $data['range'];
        if (is_array($ranges)) {
            foreach ($ranges as $k => $date) {
                if (!is_array($date)) {
                    $date = explode('-', $date);
                    $date = [
                        'from' => $date[0],
                        'to'   => $date[1],
                    ];
                }

                $from = trim($date['from']);
                $to   = trim($date['to']);

                if ($from && $to) {
                    $conditions[] = [($alias.$this->cleanField($k)).' BETWEEN ? AND ? ' => [$from, $to]];
                }

                if ($from && empty($to)) {
                    $conditions[] = [($alias.$this->cleanField($k)) => $from];
                }
            }
        }

        return $conditions;
    }

    public function ordering(&$data = [], $ordering = [])
    {
        if ($data['sort']) {
            if (empty($data['order'])) {
                $data['sort'] = implode(' ', explode('|', $data['sort'], 2));
            }
            $ordering = [trim($data['sort'].' '.$data['order'])];
        }

        return $ordering;
    }

    protected function cleanField($string)
    {
        return preg_replace("/[^\w\.\s]/", '', $string);
    }
}
