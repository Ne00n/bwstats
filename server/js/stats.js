
const trafficData = {
    labels: labels,
    datasets: [{
      label: 'Traffic',
      borderColor: 'rgb(75, 192, 192)',
      data: traffic,
      fill: {
        target: 'origin',
        above: 'rgb(255, 255, 255,0.3)',
        below: 'rgb(255, 255, 255)'    
      }
    }]
};

const storageData = {
    labels: labels,
    datasets: [{
      label: 'Storage',
      borderColor: 'rgb(75, 192, 192)',
      data: storage,
      fill: {
        target: 'origin',
        above: 'rgb(255, 255, 255,0.3)', 
        below: 'rgb(255, 255, 255)'   
      }
    }]
};

const trafficConfig = {
    type: 'line',
    data: trafficData,
    fill: true,
    options: { 
        plugins: { 
            legend: { 
                labels: {
                    boxWidth: 0
                },
            }, 
        },
        elements: {
            point:{
                radius: 0
            }
        },
        scales: {
            x: {
                ticks: {
                    maxTicksLimit: 8,
                    maxRotation: 0,
                    minRotation: 0
                },
            },
            y: {
                ticks: {
                    callback: function(value, index, values) {
                        return value + 'GB';
                    }
                }
            }
        } 
    }
};

const storageConfig = {
    type: 'line',
    data: storageData,
    options: { 
        plugins: { 
            legend: { 
                labels: {
                    boxWidth: 0
                },
            }, 
        },
        elements: {
            point:{
                radius: 0
            }
        },
        scales: {
            x: {
                ticks: {
                    maxTicksLimit: 8,
                    maxRotation: 0,
                    minRotation: 0
                },
            },
            y: {
                ticks: {
                    callback: function(value, index, values) {
                        return value + 'GB';
                    }
                }
            }
        } 
    }
};

Chart.defaults.color = "#fff";

var trafficChart = new Chart(
    document.getElementById('traffic'),
    trafficConfig
);

var storageChart = new Chart(
    document.getElementById('storage'),
    storageConfig
);