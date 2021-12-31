
const trafficData = {
    labels: labels,
    datasets: [{
      label: 'Traffic',
      borderColor: 'rgb(75, 192, 192)',
      data: traffic,
    }]
};

const storageData = {
    labels: labels,
    datasets: [{
      label: 'Storage',
      borderColor: 'rgb(75, 192, 192)',
      data: storage,
    }]
};

const trafficConfig = {
    type: 'line',
    data: trafficData,
    options: { plugins: { legend: { display: false }, } }
};

const storageConfig = {
    type: 'line',
    data: storageData,
    options: { plugins: { legend: { display: false }, } }
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