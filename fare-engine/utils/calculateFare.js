let destinationsCache = [];

async function fetchRouteOrder()
{
    try
    {
        const res =
            await fetch(
                '/passenger/index.php?route=api/destinations'
            );

        const data =
            await res.json();

        if (
            data.success &&
            Array.isArray(
                data.destinations
            )
        )
        {
            destinationsCache =
                data.destinations;
        }
    }
    catch (error)
    {
        console.error(
            'Failed to fetch destinations',
            error
        );
    }
}

function calculateFare(
    from,
    to,
    type = 'regular'
)
{
    const routeData =
        destinationsCache.length
            ? destinationsCache
            : [];

    if (
        !routeData.length
    )
    {
        console.error(
            'routeData is empty'
        );

        return 0;
    }

    type =
        (type || 'regular')
            .toLowerCase();

    const fareColumn =
        ['student', 'senior', 'senior citizen', 'pwd'].includes(type)
            ? 'student_fare'
            : ['teacher', 'nurse', 'special'].includes(type)
                ? 'special_fare'
                : 'regular_fare';

    const normalize = (str) =>
    {
        return String(str || '')
            .toLowerCase()
            .replace(', nueva ecija', '')
            .replace(/[^\w\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    };

    const getStopName = (entry) =>
        entry.destination;

    const findStop = (location) =>
    {
        const value =
            normalize(location);

        let match =
            routeData.find(
                entry =>
                    normalize(
                        getStopName(entry)
                    ) === value
            );

        if (match) return match;

        match =
            routeData.find(
                entry =>
                {
                    const stopName =
                        normalize(
                            getStopName(entry)
                        );

                    return (
                        value.includes(stopName) ||
                        stopName.includes(value)
                    );
                }
            );

        if (match) return match;

        match =
            routeData.find(
                entry =>
                {
                    const stopName =
                        normalize(
                            getStopName(entry)
                        );

                    return stopName
                        .split(' ')
                        .some(
                            word =>
                                word.length >= 4 &&
                                value.includes(word)
                        );
                }
            );

        if (match) return match;

        match =
            routeData.find(
                entry =>
                {
                    const stopName =
                        normalize(
                            getStopName(entry)
                        );

                    return value
                        .split(' ')
                        .some(
                            word =>
                                word.length >= 4 &&
                                stopName.includes(word)
                        );
                }
            );

        return match;
    };

    const boardingStop =
        findStop(from);

    const destinationStop =
        findStop(to);

    console.log({
        from,
        to,
        normalizedFrom:
            normalize(from),
        normalizedTo:
            normalize(to),
        routeCount:
            routeData.length,
        matchedFrom:
            boardingStop?.destination
            ?? 'NONE',
        matchedTo:
            destinationStop?.destination
            ?? 'NONE'
    });

    if (
        !boardingStop ||
        !destinationStop
    )
    {
        console.error(
            'STOP NOT FOUND',
            {
                from,
                to
            }
        );

        return 0;
    }

    const minimumFare = 15;

    const boardingFare =
        Number(
            boardingStop[fareColumn]
        ) || minimumFare;

    const destinationFare =
        Number(
            destinationStop[fareColumn]
        ) || minimumFare;

    // Rule 1: Boarding at terminal (display_order = 1) → fare is destination fare
    if (
        Number(boardingStop.display_order) === 1
    )
    {
        return destinationFare;
    }

    // Rule 2: Boarding after terminal → fare = destination - boarding (min ₱15)
    const fare =
        Math.max(
            destinationFare -
            boardingFare,
            minimumFare
        );

    console.log(
        'FINAL FARE:',
        fare
    );

    return fare;
}

fetchRouteOrder();

export default calculateFare;
