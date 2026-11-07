<?php
namespace xqkeji\app\attach\table\element;

use xqkeji\form\element\ListFoot as BaseListFoot;

class FootFileinput extends BaseListFoot
{
    protected $name = 'list_foot';
    protected $buttons = [
        '@BDeleteButton',
    ];
}
