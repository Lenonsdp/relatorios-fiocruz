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
		// var uniqueId = Math.floor(Math.random() * 500);
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Alarmes', class: 'alert alert-secondary', role:'alert', style: 'text-align: center;', id: 'data-alarme' + indiceGlobal })
		);
		let table = $('<table>', { class: 'table', id: 'tableAlarme' + indiceGlobal }).append(
			$('<thead>').append(
				$('<tr>', { class: 'alert alert-secondary', role:'alert'}).append(
					$('<th>', { text: 'Data' }),
					$('<th>', { text: 'Mensagem' }),
					$('<th>', { text: 'Condição' })
				)
			),
			$('<tbody>', { class: 'reportTableAlarmeBody'})
		);

		$('#reportDataCabecalho').append(table);

		Object.values(data).forEach(function (row) {
			let rowHtml = $('<tr>').append(
				$('<td>', { text: formatDataBrz(row.EventTimeStamp), style: 'width: 150px' }),
				$('<td>', { text: row.Message, style: 'width: 100px word-wrap: break-word;' }),
				$('<td>', { text: row.ConditionName, style: 'width: 150px;  word-wrap: break-word;' }),
			);

			$('.reportTableAlarmeBody').append(rowHtml);
		});
	}

	function getValueRow(fase, dataIni, dataFim, data, indice) {
		var rows = [];
		var cellsTemplate = [
			$('<td>', { style:'width: 150px;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' }),
			$('<td>', { style: 'width: 100px;text-align: end;' })
		];
		for (index in data) {
			for (index2 in data[index]['PH']) {
				if (index2 >= dataIni && index2 <= dataFim) {
					var cells = cellsTemplate.map(function(cell) { return cell.clone(); });
					cells[0].text(formatDataBrz(index2));
					cells[1].text(parseFloat(data[index]['Velocidade'][index2]).toFixed(1).replace('.', ','));
					cells[2].text(parseFloat(data[index]['Temperatura'][index2]).toFixed(1).replace('.', ','));
					cells[3].text(parseFloat(data[index]['PH'][index2]).toFixed(3).replace('.', ','));
					rows.push($('<tr>').append(cells));
				}
			}
		}

		return rows;
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
			$('<td>', { text: nroBraDecimais(row.Veloc_receita, 1), style: 'width: 80px;text-align: end;' }),
			$('<td>', { text: nroBraDecimais(row.Temperatura_receita, 1), style: 'width: 80px;text-align: end;' }),
			$('<td>', { text: nroBraDecimais(row.PH_receita, 3), style: 'width: 80px;text-align: end;' })
		);

		tbody.append(rowHtml);

		let i = 1;
		let tables = [];
		$('#reportDataCabecalho').append(
			$('<h5>', { text: 'Fases', style: 'text-align: center;', class: 'alert alert-secondary', role: 'alert', id: 'data-fase' + indiceGlobal })
		);
		Object.values(fases).forEach(function (row) {
			let headerRowHtmlFase = $('<table>', { class: 'table', 'id': 'tableFase' + indiceGlobal + i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert'}).append(
					$('<th>', { text: 'Fase', style: 'width: 150px;  word-wrap: break-word;' }),
					$('<th>', { text: 'Data inicial', style:'width: 150px;' }),
					$('<th>', { text: 'Data final', style:'width: 150px;' }),
					$('<th>', { text: 'Tempo de execução', style:'width: 150px;' })
				),
				$('<tbody>', { class: 'tableBody' }).append(
					$('<tr>').append(
						$('<td>', { text: i +'° '+row.fase, style: 'width: 150px;  word-wrap: break-word;' }),
						$('<td>', { text: formatDataBrz(row.valorMin), style:'width: 150px;' }),
						$('<td>', { text: formatDataBrz(row.valorMax), style:'width: 150px;' }),
						$('<td>', { text: calcularTempoExecucao(formatDataBrz(row.valorMin), formatDataBrz(row.valorMax)), style:'width: 150px;' })
					)
				)
			);
			tables.push(headerRowHtmlFase);

			let headerRowHtml = $('<table>', { class: 'table', 'id': 'tableFaseDados' + indiceGlobal + i}).append(
				$('<thead>', { class: 'alert alert-secondary', role: 'alert'}).append(
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
		indiceGlobal++;
	}

	function calcularTempoExecucao(dataInicialStr, dataFinalStr) {
		// Convertendo as strings de datas em objetos Date
		const dataInicial = new Date(dataInicialStr);
		const dataFinal = new Date(dataFinalStr);

		// Subtração das datas
		const diferencaEmMilissegundos = dataFinal - dataInicial;

		// Convertendo a diferença para o formato de horas, minutos, segundos e milissegundos
		const horas = Math.floor(diferencaEmMilissegundos / (1000 * 60 * 60));
		const minutos = Math.floor((diferencaEmMilissegundos % (1000 * 60 * 60)) / (1000 * 60));
		const segundos = Math.floor((diferencaEmMilissegundos % (1000 * 60)) / 1000);
		const milissegundos = diferencaEmMilissegundos % 1000;


		let resultado = '';
		if (horas > 0) {
		  resultado += `${horas}h `;
		}
		if (minutos > 0) {
		  resultado += `${minutos}m `;
		}
		resultado += `${segundos}s ${milissegundos}ms`;

		return resultado.trim();
	  }

	$('#downloadPDF').on('click', function() {
		exportarTabelaPDF();
	});

	function getHearderTable1() {
		return [
			{
				dataKey: 0, title: "Usuário", type: "text"
			},
			{
				dataKey: 1, title: "Status ciclo", type: "text"
			},
			{
				dataKey: 2, title: "Receita", type: "text"
			},
			{
				dataKey: 3, title: "Lote", type: "text"
			},
			{
				dataKey: 4, title: "Reator", type: "text"
			},
			{
				dataKey: 5, title: "Data inicial Batelada", type: "text"
			},
			{
				dataKey: 6, title: "Data final Batelada", type: "text"
			},
			{
				dataKey: 7, title: "Velocidade", type: "text"
			},
			{
				dataKey: 8, title: "Temperatura", type: "text"
			},
			{
				dataKey: 9, title: "PH", type: "text"
			}
		];
	}

	function getHeaderTable2() {
		return [
			{
				dataKey: 0, title: "Fase", type: "text"
			},
			{
				dataKey: 1, title: "Data inicial", type: "text"
			},
			{
				dataKey: 2, title: "Data final", type: "text"
			}
		];
	}

	function getHeaderTable3() {
		return [
			{
				dataKey: 0, title: "Tempo de execução", type: "text"
			},
			{
				dataKey: 1, title: "Tempo de inatividade", type: "text"
			},
			{
				dataKey: 2, title: "Data e Hora", type: "text"
			},
			{
				dataKey: 3, title: "Rotação", type: "text"
			},
			{
				dataKey: 4, title: "Temperatura", type: "text"
			},
			{
				dataKey: 5, title: "PH", type: "text"
			}
		]
	}

	function getDataTable1(indice) {
		var data = [];

		$('#data-table-cabecalho' + indice).find('tbody tr').each(function() {
			var row = [];
			$(this).find('td').each(function() {
				row.push($(this).text());
			});
			data.push(row);
		});
		return data;
	}

	function getDataTable2(indice) {
		var data = [];
		$('#tableFase' + indice + 0).find('tbody tr').each(function() {
			var row = [];
			$(this).find('td').each(function() {
				row.push($(this).text());
			});
			data.push(row);
		});

		return data;
	}

	function getDataTable3(indice) {
		debugger
		var data = [];
		$('#tableFaseDados' + indice + 0).find('tbody tr').each(function() {
			var row = [];
			$(this).find('td').each(function() {
				row.push($(this).text());
			});
			data.push(row);
		});
		return data;
	}

	function exportarTabelaPDF() {
		var columns = [];
		var columnAlign = [];
		var alignCol = [];
		var doc = new jsPDF('l', 'pt', 'a4');
		for (let i = 0; i < indiceGlobal; i++) {
			const tableData1 = [
				getHearderTable1(),
				...getDataTable1(i),
			];

			const tableData2 = [
				getHeaderTable2(),
				...getDataTable2(i)
			];


			const tableData3 = [
				getHeaderTable3(),
				...getDataTable3(i)
			]

			doc = createTable(doc, tableData1, 10, i * 4 * 10);
			doc = createTable(doc, tableData2, 10, i * 4 * 100);
			doc = createTable(doc, tableData3, 10, i * 4 * 300);
		}

		doc.save('table.pdf');

		return;
		var inicio = 20;
		orientacao = ('l') //p porta retrato l paisagem;
		var pdf = new jsPDF(orientacao, 'pt', 'a4');
		if (false) {
			pdf.addImage(header, 'jpeg', Math.round((pdf.internal.pageSize.width - dimensao.width) / 2), inicio, dimensao.width, dimensao.height);
			inicio += 130;
		}

		// if (titulo) {
		// 	pdf.setFontSize(14);
		// 	var textWidth = pdf.getStringUnitWidth(titulo) * pdf.internal.getFontSize() / pdf.internal.scaleFactor;
		// 	var textOffset = (pdf.internal.pageSize.width - textWidth) / 2;
		// 	pdf.text(textOffset, inicio, titulo);
		// 	inicio += 10;
		// }

		pdf.autoTable(columns, data, {
			theme: 'grid',
			startY: inicio,
			headerStyles: {
				fillColor: [187,187,187],
				textColor: 255,
				rowHeight: 15,
				valign: 'middle'
			},
			styles: {
				overflow: 'linebreak',
				fontSize: 8,
			},
			columnStyles: alignCol,
			margin: 20
		});

		if (!fileName) {
			fileName = 'exportacao-' + new Date().toJSON().slice(0,10);
		}

		pdf.save(fileName + '.pdf');
	}

	// Função para criar uma tabela usando jsPDF
	function createTable(doc, data, x, y) {
		const margin = 10;

		doc.autoTable(data[0], data.slice(1), {
			theme: 'grid',
			startY: y + margin,
			headerStyles: {
				fillColor: [187,187,187],
				textColor: 255,
				rowHeight: 15,
				valign: 'middle'
			},
			styles: {
				overflow: 'linebreak',
				fontSize: 8,
			},
			margin: 10
		});
		return doc;
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
