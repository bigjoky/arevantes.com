import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

export type MonitoringPoint = {
    provider: string;
    status: string | null;
    current_power_kw: string | null;
    today_energy_kwh: string | null;
    recorded_at: string;
};

export default function MonitoringChart({
    points,
}: {
    points: MonitoringPoint[];
}) {
    const data = points.map((point) => ({
        time: new Intl.DateTimeFormat('es-ES', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(point.recorded_at)),
        power: Number(point.current_power_kw || 0),
        energy: Number(point.today_energy_kwh || 0),
    }));

    if (data.length < 2) {
        return (
            <div className="flex h-64 items-center justify-center text-sm text-muted-foreground">
                Not enough historical readings yet.
            </div>
        );
    }

    return (
        <div
            className="h-72 w-full"
            aria-label="Power and daily energy history"
        >
            <ResponsiveContainer width="100%" height="100%">
                <LineChart
                    data={data}
                    margin={{ top: 8, right: 16, bottom: 0, left: 0 }}
                >
                    <CartesianGrid strokeDasharray="3 3" vertical={false} />
                    <XAxis
                        dataKey="time"
                        minTickGap={36}
                        tick={{ fontSize: 11 }}
                    />
                    <YAxis yAxisId="power" tick={{ fontSize: 11 }} width={42} />
                    <YAxis
                        yAxisId="energy"
                        orientation="right"
                        tick={{ fontSize: 11 }}
                        width={42}
                    />
                    <Tooltip />
                    <Line
                        yAxisId="power"
                        type="monotone"
                        dataKey="power"
                        name="Power kW"
                        stroke="#059669"
                        strokeWidth={2}
                        dot={false}
                    />
                    <Line
                        yAxisId="energy"
                        type="monotone"
                        dataKey="energy"
                        name="Today kWh"
                        stroke="#0284c7"
                        strokeWidth={2}
                        dot={false}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}
