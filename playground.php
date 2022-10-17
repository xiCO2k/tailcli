<?php

require_once __DIR__.'/vendor/autoload.php';

use function Termwind\{live, terminal};

$times = 0;

live(function ($refresh, $mouse, $click) use (&$times) {
    if ($click) {
        $times++;
    }

    return <<<HTML
        <div class="mx-2 my-1 space-y-1 max-w-80">
            <div class="font-bold text-black bg-green-500 px-1 w-full text-center">
                What if I tell you you can detect <u>hover</u> and <u>click</u> events?
            </div>
            <div class="flex justify-evenly font-bold">
                <span class="bg-blue-300 text-black hover:bg-blue-600 px-2">
                    <span class="hover:underline">hover me!</span>
                </span>
                <span
                    class="bg-purple-300 text-black px-2"
                    @click="$times++"
                >
                    click me!
                </span>
            </div>
            <div class="w-full text-center">You clicked me <b>{$times}</b> times!</div>
        </div>
    HTML;
})->refreshEvery(seconds: 1);
