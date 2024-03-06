$(document).ready(function(){
	indiceGlobal = 0;

	$('#startDate, #endDate').datepicker({
		language: 'pt-BR'
	});
	$('#operator').keyboard({
		usePreview: false,
		useWheel: false,
		autoAccept: true,
		maxLength: 20,
		restrictInput: true,
		change: function(e, keyboard, el) {
		  // Atualize o valor do campo de entrada
		  $('#operator').val(el.value);
		}
	  });

	  $('#lot').keyboard({
		usePreview: false,
		useWheel: false,
		autoAccept: true,
		maxLength: 20,
		restrictInput: true,
		change: function(e, keyboard, el) {
		  // Atualize o valor do campo de entrada
		  $('#lot').val(el.value);
		}
	  });

	function parseDate(dateString) {
		var parts = dateString.split('/');
		return new Date(parts[2], parts[1] - 1, parts[0]); // Ano, mês (0-11), dia
	}

	function validar() {
		if ($('#startDate').val() == '' || $('#endDate').val() == '') {
			$('.modal-title').text('Aviso');
			$('.modal-body').text('Data inicial e data final devem ser preenchidas');
			$('#modal').modal('show');
			return false;
		}

		var startDate = parseDate($('#startDate').val());
		var endDate = parseDate($('#endDate').val());

		if (startDate > endDate) {
			$('.modal-title').text('Aviso');
			$('.modal-body').text('Data inicial não pode ser maior que a data final');
			$('#modal').modal('show');
			return false;
		}

		// Calcula a diferença em milissegundos entre as datas
		var timeDiff = Math.abs(startDate.getTime() - endDate.getTime());

		// Calcula o número de dias de diferença
		var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

		if (diffDays > 3) {
			$('.modal-title').text('Aviso');
			$('.modal-body').text('A diferença entre as datas não pode ser maior que 3 dias');
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


	function fillTable(data) {
		if (data.length === 0) {
			alert('Nenhum registro encontrado');
			return;
		}
		var fases = [];
		indiceGlobal = 0;
		$('#reportTableBody, #reportDataCabecalho, #reportTableAlarme').empty();
		for (index in data) {
			for (index2 in data[index]['Fase']) {
				if (index2.trim().length) {
					fases.push({'lote': index, 'fase': index2, 'valorMin': data[index]['Fase'][index2][0], 'valorMax': data[index]['Fase'][index2].pop()});
				}
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
		// var uniqueId = Math.floor(Math.random() * 500);
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Alarmes', class: 'alert alert-secondary', role:'alert', style: 'text-align: center;', id: 'data-alarme' + indiceGlobal })
		);
		let table = $('<table>', { class: 'table', id: 'tableAlarme' + indiceGlobal }).append(
			$('<thead>').append(
				$('<tr>', { class: 'alert alert-secondary', role:'alert'}).append(
					$('<th>', { text: 'Data' }),
					$('<th>', { text: 'Mensagem' })
				)
			),
			$('<tbody>', { class: 'reportTableAlarmeBody'})
		);

		$('#reportDataCabecalho').append(table);

		Object.values(data).forEach(function (row) {
			let rowHtml = $('<tr>').append(
				$('<td>', { text: formatDataBrz(row.EventTimeStamp, false), style: 'width: 250px' }),
				$('<td>', { text: row.Message, style: 'width: 600px word-wrap: break-word;' })
			);

			$('.reportTableAlarmeBody').append(rowHtml);
		});
	}

	function getValueRow(fase, dataIni, dataFim, data, indice) {
		var rows = [];
		var cellsTemplate = [
			$('<td>', { style:'width: 150px;' }),
			$('<td>', { style: 'width: 100px;' }),
			$('<td>', { style: 'width: 100px;' }),
			$('<td>', { style: 'width: 100px;' })
		];
		for (index in data) {
			for (index2 in data[index]['PH']) {
				if (index2 >= dataIni && index2 <= dataFim) {
					var cells = cellsTemplate.map(function(cell) { return cell.clone(); });
					cells[0].text(formatDataBrz(index2, false));
					cells[1].text(parseInt(data[index]['Velocidade'][index2]) + '%');
					cells[2].text(parseFloat(data[index]['Temperatura'][index2]).toFixed(1).replace('.', ',') + '°C');
					cells[3].text(parseFloat(data[index]['PH'][index2]).toFixed(2).replace('.', ',') + ' pH');
					rows.push($('<tr>').append(cells));
				}
			}
		}

		return rows;
	}

	function getValueReceita(valor, row, data, data2) {
		if (valor == 'temperatura') {
			return 'de ' + nroBraDecimais(data[row], 1) + '°C a ' + nroBraDecimais(data2[row], 1) + '°C';
		}
		if (valor == 'rotacao') {
			return nroBraDecimais(data[row], 0)+'%';
		}
		if (valor == 'ph') {
			return 'de ' + nroBraDecimais(data[row], 2) + ' pH a ' + nroBraDecimais(data2[row], 2) + ' pH';
		}

	}

	function buildReportData(row, fases, data) {
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Lote: ' + row.Num_Lote, class: 'alert alert-secondary', role:'alert', style: 'text-align: center;', id: 'data-lote' + indiceGlobal  })
		);
		let table = $('<table>', { class: 'table', id: 'data-table-cabecalho' + indiceGlobal });
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
			$('<th>', { text: 'Data final Batelada', style:'width: 150px;' })
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
			$('<td>', { text: formatDataBrz(row.dataMax, false), style: 'width: 150px' })
		);

		tbody.append(rowHtml);

		let i = 1;
		let tables = [];
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Fases', style: 'text-align: center;background-color: #8d8d8db0;', class: 'alert alert-secondary', role: 'alert', id: 'data-fase' + indiceGlobal })
		);
		Object.values(fases).forEach(function (row) {
			let headerRowHtmlFase = $('<table>', { class: 'table', 'id': 'tableFase' + indiceGlobal + i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert', style: 'background-color: #8d8d8db0;'}).append(
					$('<th>', { text: 'Fase', style: 'width: 180px;  word-wrap: break-word;' }),
					$('<th>', { text: 'Data inicial', style:'width: 180px;' }),
					$('<th>', { text: 'Data final', style:'width: 180px;' }),
					$('<th>', { text: 'Tempo de execução', style:'width: 100px;' }),
					$('<th>', { text: 'Amplitude', style:'width: 15px;' }),
					$('<th>', { text: 'Temperatura', style:'width: 180px;' }),
					$('<th>', { text: 'pH', style:'width: 180px;' })
				),
				$('<tbody>', { class: 'tableBody' }).append(
					$('<tr>').append(
						$('<td>', { text: i +'° '+row.fase, style: 'width: 180px;  word-wrap: break-word;' }),
						$('<td>', { text: formatDataBrz(row.valorMin, false), style:'width: 180px;' }),
						$('<td>', { text: formatDataBrz(row.valorMax, false), style:'width: 180px;' }),
						$('<td>', { text: calcularTempoExecucao(formatDataBrz(row.valorMin), formatDataBrz(row.valorMax)), style:'width: 30px;' }),
						$('<td>', { text: getValueReceita('rotacao', row.valorMin, data[row.lote].Veloc_receita, ''), style: 'width: 15px;' }),
						$('<td>', { text: getValueReceita('temperatura',row.valorMin, data[row.lote].Temperatura_receita_min, data[row.lote].Temperatura_receita_max), style: 'width: 180px;' }),
						$('<td>', { text: getValueReceita('ph', row.valorMin, data[row.lote].PH_receita_min, data[row.lote].PH_receita_max), style: 'width: 180px;' })
					)
				)
			);
			tables.push(headerRowHtmlFase);

			let headerRowHtml = $('<table>', { class: 'table', 'id': 'tableFaseDados' + indiceGlobal + i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert'}).append(
					$('<th>', { text: 'Data e Hora' }),
					$('<th>', { text: 'Amplitude', style: 'width: 100px;' }),
					$('<th>', { text: 'Temperatura', style: 'width: 150px;' }),
					$('<th>', { text: 'pH', style: 'width: 100px;' })
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
		indiceGlobal++;
	}

	function calcularTempoExecucao(dataInicialStr, dataFinalStr) {
		// Função para converter string de data para objeto Date
		const converterParaData = (dataStr) => {
			const [dataParte, horaParte] = dataStr.split(' ');
			const [dia, mes, ano] = dataParte.split('/');
			const [hora, minuto, segundo] = horaParte.split(':');

			return new Date(ano, mes - 1, dia, hora, minuto, segundo);
		};

		// Convertendo as strings de datas em objetos Date
		const dataInicial = converterParaData(dataInicialStr);
		const dataFinal = converterParaData(dataFinalStr);

		// Subtração das datas
		const diferencaEmMilissegundos = dataFinal - dataInicial;

		// Convertendo a diferença para o formato de horas, minutos, segundos
		const horas = Math.floor(diferencaEmMilissegundos / (1000 * 60 * 60));
		const minutos = Math.floor((diferencaEmMilissegundos % (1000 * 60 * 60)) / (1000 * 60));
		const segundos = Math.floor((diferencaEmMilissegundos % (1000 * 60)) / 1000);

		let resultado = '';
		if (horas > 0) {
			resultado += `${horas}h `;
		}
		if (minutos > 0) {
			resultado += `${minutos}m `;
		}
		resultado += `${segundos}s`;

		return resultado.trim();
	}

	function formatDataBrz(dt, returnMilliseconds = true) {
		var date = new Date(dt);
		var day = ("0" + date.getDate()).slice(-2);
		var month = ("0" + (date.getMonth() + 1)).slice(-2);
		var year = date.getFullYear();
		var hours = ("0" + date.getHours()).slice(-2);
		var minutes = ("0" + date.getMinutes()).slice(-2);
		var seconds = ("0" + date.getSeconds()).slice(-2);
		var milliseconds = ("00" + date.getMilliseconds()).slice(-3);
		// if (returnMilliseconds) {
		// 	return day + "/" + month + "/" + year + " " + hours + ":" + minutes + ":" + seconds + "." + milliseconds;
		// }
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
