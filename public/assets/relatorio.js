$(document).ready(function(){

	$('#startDate, #endDate').datepicker({
		language: 'pt-BR'
	});
	function validar() {
		if ($('#startDate').val() == '' || $('#endDate').val() == '') {
			$('.modal-title').text('Aviso');
			$('.modal-body').text('Data inicial e data final devem ser preenchidas');
			$('#modal').modal('show');
			return false;
		}

		if ($('#startDate').val() > $('#endDate').val()) {
			$('.modal-title').text('Aviso');
			$('.modal-body').text('Data inicial não pode ser maior que a data final');
			$('#modal').modal('show');
			return false;
		}
		return true;
	}
	$('#filterForm').submit(function (e) {
		$('#reportDataCabecalho').empty();
		e.preventDefault();
		let startDate = $('#startDate').val();
		let endDate = $('#endDate').val();
		let operator = $('#operator').val();
		let lot = $('#lot').val();
		if(!validar()) {
			return;
		}
		$.ajax({
			method: 'GET',
			url: '/relatorio',
			data: {
				startDate: startDate,
				endDate: endDate,
				operator: operator,
				lote: lot
			},
			beforeSend: function () {
				$('.spinner-div').css('display', 'flex').show();
			},
			success: function (response) {
				if (response.length === 0) {
					$('#modal').modal('show');
					$('.modal-title').text('Aviso');
					$('.modal-body').text('Nenhum registro encontrado');
					return;
				}
				if (response.manyLotesError) {
					$('#modal').modal('show');
					$('.modal-title').text('Aviso');
					$('.modal-body').text('Sua busca retornou mais de 50 lotes, não foi possível listar os dados, por favor defina um periodo menor');
					$('.spinner-div').hide();
					return;
				}
				if (response.manyLotesWarning) {
					$('#modal').modal('show');
					$('.modal-title').text('Aviso');
					$('.modal-body').text('Sua busca retornou mais de 30 lotes, por favor, listamos apenas os 30 registros, por favor defina um periodo menor');
					$('.spinner-div').hide();
				}
				delete response.manyLotesWarning;
				fillTable(response);
			},
			error: function (error) {
				$('#modal').modal('show');
				$('.modal-title').text('Error');
				$('.modal-body').text('Estamos com problemas técnicos, tente novamente mais tarde');
				console.error(error);
			},
			complete: function () {
				$('.spinner-div').hide();
			}
		});
	});
	function compararDataMin($a, $b) {
		return strtotime($a['dataMin']) - strtotime($b['dataMin']);
	}
	function fillTable(data) {
		if (data.length === 0) {
			alert('Nenhum registro encontrado');
			return;
		}
		var fases = [];
		$('#reportTableBody, #reportDataCabecalho, #reportTableAlarme').empty();
		for (index in data) {
			for (index2 in data[index]['Fase']) {
				fases.push({'lote': index, 'fase': index2, 'valorMin': data[index]['Fase'][index2][0], 'valorMax': data[index]['Fase'][index2].pop()});
			}
		}

		const datas = Object.entries(data).map(([key, value]) => ({ key, ...value }));
		Object.values(datas.sort((a, b) => new Date(a.dataMax) - new Date(b.dataMax))).forEach(function (row) {
			let filteredData = Object.keys(data)
				.filter(key => key == row.Num_Lote)
				.reduce((obj, key) => {
					obj[key] = data[key];
					return obj;
				}, {});
			buildReportData(row, fases.filter(fase => fase.lote === row.Num_Lote), filteredData);
		});
	}

	function buildAlarmeTable(data) {
		if (!data.length) {
			return;
		}
		var uniqueId = Math.floor(Math.random() * 500);
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Alarmes', class: 'alert alert-secondary', role:'alert', style: 'text-align: center;' })
		);
		let table = $('<table>', { class: 'table' }).append(
			$('<thead>').append(
				$('<tr>', { class: 'alert alert-secondary', role:'alert'}).append(
					$('<th>', { text: 'Data' }),
					$('<th>', { text: 'Mensagem' }),
					$('<th>', { text: 'Condição' })
				)
			),
			$('<tbody>', { class: 'reportTableAlarmeBody' + uniqueId })
		);

		$('#reportDataCabecalho').append(table);

		Object.values(data).forEach(function (row) {
			let rowHtml = $('<tr>').append(
				$('<td>', { text: formatDataBrz(row.EventTimeStamp), style: 'width: 150px' }),
				$('<td>', { text: row.Message, style: 'width: 100px word-wrap: break-word;' }),
				$('<td>', { text: row.ConditionName, style: 'width: 150px;  word-wrap: break-word;' }),
			);

			$('.reportTableAlarmeBody' +uniqueId).append(rowHtml);
		});
	}

	function getValueRow(fase, dataIni, dataFim, data, indice) {
		var rows = [];
		var cellsTemplate = [
			$('<td>', { style:'width: 60px;' }),
			$('<td>', { style:'width: 60px;' }),
			$('<td>', { style:'width: 150px;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' })
		];
		for (index in data) {
			for (index2 in data[index]['PH']) {
				if (index2 >= dataIni && index2 <= dataFim) {
					var cells = cellsTemplate.map(function(cell) { return cell.clone(); });
					cells[0].text(data[index]['Tempo_execucao'][index2]);
					cells[1].text(data[index]['Tempo_inativo'][index2]);
					cells[2].text(formatDataBrz(index2));
					cells[3].text(parseFloat(data[index]['Velocidade'][index2]).toFixed(1).replace('.', ','));
					cells[4].text(parseFloat(data[index]['Temperatura'][index2]).toFixed(1).replace('.', ','));
					cells[5].text(parseFloat(data[index]['PH'][index2]).toFixed(3).replace('.', ','));
					rows.push($('<tr>').append(cells));
				}
			}
		}

		return rows;
	}
	function buildReportData(row, fases, data) {
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Lote: ' + row.Num_Lote, class: 'alert alert-secondary', role:'alert', style: 'text-align: center;' })
		);
		let table = $('<table>', { class: 'table' });
		let thead = $('<thead>');
		let tbody = $('<tbody>', { class: 'reportTableCabecalhoBody' });
		let tr = $('<tr>', { class: 'alert alert-secondary', role:'alert'});
		let ths = [
			$('<th>', { text: 'Usuário'}),
			$('<th>', { text: 'Status ciclo' }),
			$('<th>', { text: 'Receita' }),
			$('<th>', { text: 'Lote' }),
			$('<th>', { text: 'Reator' }),
			$('<th>', { text: 'Data inicial Batelada', style:'width: 150px;' }),
			$('<th>', { text: 'Data final Batelada', style:'width: 150px;' }),
			$('<th>', { text: 'Velocidade', style:'width: 80px;' }),
			$('<th>', { text: 'Temperatura', style:'width: 100px;' }),
			$('<th>', { text: 'PH', style:'width: 80px; text-align: end;' })
		];

		tr.append(ths);
		thead.append(tr);
		table.append(thead, tbody);
		$('#reportDataCabecalho').append(table);

		let rowHtml = $('<tr>').append(
			$('<td>', { text: row.NomeUsuario, style: 'width: 150px;  word-wrap: break-word;' }),
			$('<td>', { text: row.Ciclo_ok_nok, style: 'width: 100px word-wrap: break-word;' }),
			$('<td>', { text: row.Nome_receita, style: 'width: 100px word-wrap: break-word;' }),
			$('<td>', { text: row.Num_Lote, style: 'width: 100px word-wrap: break-word;' }),
			$('<td>', { text: row.ID_Dorna, style: 'width: 100px word-wrap: break-word;' }),
			$('<td>', { text: formatDataBrz(row.dataMin, false), style: 'width: 150px' }),
			$('<td>', { text: formatDataBrz(row.dataMax, false), style: 'width: 150px' }),
			$('<td>', { text: row.Veloc_receita, style: 'width: 80px;text-align: end;' }),
			$('<td>', { text: row.Temperatura_receita, style: 'width: 80px;text-align: end;' }),
			$('<td>', { text: row.PH_receita, style: 'width: 80px;text-align: end;' })
		);

		tbody.append(rowHtml);

		let i = 1;
		let tables = [];
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Fases', style: 'text-align: center;', class: 'alert alert-secondary', role: 'alert' })
		);
		Object.values(fases).forEach(function (row) {
			let headerRowHtmlFase = $('<table>', { class: 'table', 'id': 'table' + i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert'}).append(
					$('<th>', { text: 'Fase', style: 'width: 150px;  word-wrap: break-word;' }),
					$('<th>', { text: 'Data inicial', style:'width: 150px;' }),
					$('<th>', { text: 'Data final', style:'width: 150px;' }),
				),
				$('<tbody>', { class: 'tableBody' }).append(
					$('<tr>').append(
						$('<td>', { text: i +'° '+row.fase, style: 'width: 150px;  word-wrap: break-word;' }),
						$('<td>', { text: formatDataBrz(row.valorMin), style:'width: 150px;' }),
						$('<td>', { text: formatDataBrz(row.valorMax), style:'width: 150px;' }),
					)
				)
			);
			tables.push(headerRowHtmlFase);

			let headerRowHtml = $('<table>', { class: 'table', 'id': i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert'}).append(
					$('<th>', { text: 'Tempo de execução', style: 'width: 100px;' }),
					$('<th>', { text: 'Tempo de inatividade',  style: 'width: 100px;' }),
					$('<th>', { text: 'Data e Hora' }),
					$('<th>', { text: 'Rotação', style: 'width: 100px;text-align: end;' }),
					$('<th>', { text: 'Temperatura', style: 'width: 150px;text-align: end;' }),
					$('<th>', { text: 'PH', style: 'width: 100px; text-align: end;' })
				),
				$('<tbody>', { class: 'tableBody' }).append(
					getValueRow(row.fase, row.valorMin, row.valorMax, data, i)
				)
			);
			tables.push(headerRowHtml);
			i++;
		});

		$('#reportDataCabecalho').append(tables);

		buildAlarmeTable(row.Alarme);
	}

	// var doc = new jsPDF('l', 'mm', 'a4');
	// $('#downloadPDF').on('click', function() {
	// 	$.ajax({
	// 		url: '/impressao',
	// 		method: 'GET',
	// 		success: function(response) {
	// 			var customHtml = response;
	// 			var options = {
	// 				pagesplit: true // Permite que o conteúdo seja dividido em várias páginas, se necessário
	// 			};
	// 			doc.fromHTML(response, 40, 15, {
	// 				'width': 170
	// 			});
	// 			doc.save('exemplo-pdf.pdf');
	// 		},
	// 		error: function(error) {
	// 			console.error(error);
	// 		}
	// 	});
	// });

	// $('#downloadExcel').on('click', function() {
	// 	downloadExcel();
	// });

	function formatDataBrz(dt, returnMilliseconds = true) {
		var date = new Date(dt);
		var day = ("0" + date.getDate()).slice(-2);
		var month = ("0" + (date.getMonth() + 1)).slice(-2);
		var year = date.getFullYear();
		var hours = ("0" + date.getHours()).slice(-2);
		var minutes = ("0" + date.getMinutes()).slice(-2);
		var seconds = ("0" + date.getSeconds()).slice(-2);
		var milliseconds = ("00" + date.getMilliseconds()).slice(-3);
		if (returnMilliseconds) {
			return day + "/" + month + "/" + year + " " + hours + ":" + minutes + ":" + seconds + "." + milliseconds;
		}
		return day + "/" + month + "/" + year + " " + hours + ":" + minutes + ":" + seconds;
	}

	function nroBraDecimais(nro, decimais) {
		nro = (nro + '').replace(/[^0-9+\-Ee.]/g, '');
		var n = (!isFinite(+nro) ? 0 : +nro);
		var	prec = (!isFinite(+decimais) ? 0 : Math.abs(decimais));
		var s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');

		if (s[0].length > 3) {
			s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, '.');
		}

		s[1] = s[1] || '';
		if (s[1].length < prec) {
			s[1] += new Array(prec - s[1].length + 1).join('0');
		}

		return s.join(prec ? ',' : '');
	}

	function toFixedFix(n, prec, parse) {
		var k = Math.pow(10, prec);
		var number = Math.round(Math.abs(n) * k) / k;
		var s = (n < 0 && number != 0 ? '-' : '') + number;

		return (parse ? parseFloat(s) : s);
	}

});
