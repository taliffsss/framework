var ctx = document.getElementById("myChart").getContext('2d');
var myChart = new Chart(ctx, {
	type: 'bar',
	data: {
			labels: ["Day 1","Day 2","Day 3","Day 4","Day 5","Day 6","Day 7","Day 8","Day 9","Day 10",">10","Cancelled"],
			datasets: [{
					label: 'Turnaround Time',
					data: [56,87,72,72,72,72,72,72,72,72,72,72],
					backgroundColor: [
							'rgba(179, 49, 49, 0.6)',
							'rgba(53, 127, 204, 0.6)',
							'rgba(255, 206, 86, 0.6)',
							'rgba(75, 192, 192, 0.6)',
							'rgba(153, 102, 255, 0.6)',
							'rgba(255, 159, 64, 0.6)',
							'rgba(192, 92, 177, 0.6)',
							'rgba(75, 134, 100, 0.6)',
							'rgba(192, 131, 82, 0.6)',
							'rgba(43, 52, 93, 0.6)',
							'rgba(186, 122, 184, 0.6)',
							'rgba(192, 192, 192, 0.6)',
					],
					borderColor: [
							'rgba(179, 49, 49,1)',
							'rgba(53, 127, 204, 1)',
							'rgba(255, 206, 86, 1)',
							'rgba(75, 192, 192, 1)',
							'rgba(153, 102, 255, 1)',
							'rgba(255, 159, 64, 1)',
							'rgba(171, 72, 156, 1)',
							'rgba(31, 105, 63, 1)',
							'rgba(151, 89, 39, 1)',
							'rgba(21, 33, 93, 1)',
							'rgba(193, 61, 188, 1)',
							'rgba(135, 135, 135, 1)',
					],
					borderWidth: 1
			}]
	},
	options: {
		legend: {
	    	display: false
	    },
	    tooltips: {
	    	callbacks: {
	      	label: function(tooltipItem) {
	        console.log(tooltipItem)
	        	return tooltipItem.yLabel;
	        }
	      }
	    },
		scales: {
			yAxes: [{
				ticks: {
					beginAtZero:true
				},
				scaleLabel: {
			        display: true,
			        labelString: 'No of Occurrences'
			    }
			}],
			xAxes: [{
				scaleLabel: {
			        display: true,
			        labelString: 'No of Days'
			    }
			}]
		}
	}
});