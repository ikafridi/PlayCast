<?php

declare(strict_types=1);

namespace App\Controller\Stations\Reports;

use App\Http\Response;
use App\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;

class TimelineAction
{
    public function __invoke(ServerRequest $request, Response $response): ResponseInterface
    {
        $router = $request->getRouter();
        $station = $request->getStation();

        return $request->getView()->renderToResponse(
            $response,
            'system/vue',
            [
                'title' => __('Song Playback Timeline'),
                'id' => 'station-report-timeline',
                'component' => 'Vue_StationsReportsTimeline',
                'props' => [
                    'baseApiUrl' => (string)$router->fromHere('api:stations:history'),
                    'stationTimeZone' => $station->getTimezone(),
                ],
            ]
        );
    }
}
