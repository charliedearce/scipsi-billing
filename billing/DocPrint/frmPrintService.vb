Imports System.Data
Imports System.Data.SqlClient
Imports System.Text

Public Class frmPrintService
    Dim qty As String
    Dim unit As String
    Dim service As String
    Dim cargo As String
    Dim rate As String
    Dim gross As String
    Dim cargoname As String
    Private Sub frmPrintService_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        Try

            myCon.open()
            myCmd = New SqlCommand("SELECT it_qty,it_unit,it_service,it_ccode,it_cargo,it_rate,it_gross FROM tbl_item_trans WHERE it_bill_num = '" & frmBilling.txtBillno.Text & "'", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows = True Then
                Do While myReader.Read()
                    qtybox.Items.Add(myReader("it_qty"))
                    unitbox.Items.Add(myReader("it_unit"))
                    servicebox.Items.Add(myReader("it_service"))
                    cargobox.Items.Add(myReader("it_ccode"))
                    ratebox.Items.Add(myReader("it_rate"))
                    grossbox.Items.Add(myReader("it_gross"))
                    cargodesc.Items.Add(myReader("it_cargo"))
                Loop
            End If

            Dim qtybuilder As New StringBuilder()
            For Each i As Object In qtybox.Items
                qtybuilder.AppendLine(i.ToString())
            Next
            qty = qtybuilder.ToString()

            Dim unitbuilder As New StringBuilder()
            For Each i As Object In unitbox.Items
                unitbuilder.AppendLine(i.ToString())
            Next
            unit = unitbuilder.ToString()

            Dim servicebuilder As New StringBuilder()
            For Each i As Object In servicebox.Items
                servicebuilder.AppendLine(i.ToString())
            Next
            service = servicebuilder.ToString()

            Dim cargobuilder As New StringBuilder()
            For Each i As Object In cargobox.Items
                cargobuilder.AppendLine(i.ToString())
            Next
            cargo = cargobuilder.ToString()

            Dim ratebuilder As New StringBuilder()
            For Each i As Object In ratebox.Items
                ratebuilder.AppendLine(i.ToString())
            Next
            Rate = ratebuilder.ToString()

            Dim grossbuilder As New StringBuilder()
            For Each i As Object In grossbox.Items
                grossbuilder.AppendLine(i.ToString())
            Next
            gross = grossbuilder.ToString()

            Dim cargodescbuilder As New StringBuilder()
            For Each i As Object In cargodesc.Items
                cargodescbuilder.AppendLine(i.ToString())
            Next
            cargoname = cargodescbuilder.ToString()

            myCmd.Dispose()
            myReader.Close()

            Dim mycmd1 = New SqlCommand("SELECT myprint FROM tbl_settings", myCon)
            myReader = mycmd1.ExecuteReader
            If myReader.HasRows Then
                If myReader.Read = True Then
                    If myReader("myprint") = "YES" Then
                        PrintDocument1.Print()
                    End If
                End If
            End If

            mycmd1.dispose()
            myReader.Close()

            Dim myCmd2 = New SqlCommand("UPDATE tbl_bill_trans SET bt_indiprint='Y' WHERE bt_bill_num='" & frmBilling.txtBillno.Text & "'", myCon)
            myCmd2.ExecuteNonQuery()

            myCmd2.dispose()

            myCon.close()

        Catch ex As Exception
            MsgBox(ex.Message)
        End Try
        Me.Dispose()
    End Sub

    Private Sub PrintDocument1_PrintPage(sender As Object, e As Printing.PrintPageEventArgs) Handles PrintDocument1.PrintPage
        Dim sysdate As Date = frmBilling.txtTime.Text
        Dim mydate As String
        If frmBilling.lbldatetrans.Text = "" Then
            mydate = sysdate.ToString("MM/dd/yyyy")
        Else
            mydate = frmBilling.lbldatetrans.Text
        End If
        e.Graphics.DrawString(frmBilling.txtBillno.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(620, 96)) 'bill
        e.Graphics.DrawString(frmBilling.txtAccountno.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(70, 125)) 'account
        e.Graphics.DrawString(frmBilling.lblAccname.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(70, 140)) 'account
        e.Graphics.DrawString("VESSEL: " + frmBilling.txtVessel.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(80, 200)) 'vessel
        e.Graphics.DrawString("VOYAGE: " + frmBilling.txtVoyage.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(320, 200)) 'voyage
        e.Graphics.DrawString("IN/OUT: " + frmBilling.cbType.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(600, 200)) 'type
        e.Graphics.DrawString(frmBilling.txtParticulars.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(450, 170)) 'particulars
        e.Graphics.DrawString(mydate, New Font("Microsoft San Serif", 9), Brushes.Black, New Point(620, 160)) 'date
        e.Graphics.DrawString(frmBilling.txtName.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(590, 445)) 'employee
        e.Graphics.DrawString(qty, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(5, 237)) 'qty
        e.Graphics.DrawString(unit, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(70, 237)) 'unit
        e.Graphics.DrawString(service, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(130, 237)) 'service
        e.Graphics.DrawString(cargo, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(390, 237)) 'cargo
        'e.Graphics.DrawString(cargoname, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(395, 227)) 'cargo name
        e.Graphics.DrawString(rate, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(540, 237)) 'rate
        e.Graphics.DrawString(gross, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(620, 237)) 'gross

        e.Graphics.DrawString("TOTAL CHARGES:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(210, 370)) 'gross
        e.Graphics.DrawString(frmBilling.txtTotal.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 383)) 'gross
        e.Graphics.DrawString("PPA SHARE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(210, 396)) 'gross
        e.Graphics.DrawString(frmBilling.txtLessppa.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 409)) 'gross

        e.Graphics.DrawString("TOTAL DISCOUNT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(355, 370)) 'gross
        e.Graphics.DrawString(frmBilling.txtDiscount.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(365, 383)) 'gross
        e.Graphics.DrawString("SCIPSI SHARE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(355, 396)) 'gross
        e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(365, 409)) 'gross

        e.Graphics.DrawString("12% VAT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(500, 370)) 'gross
        e.Graphics.DrawString(frmBilling.txtVat.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(510, 383)) 'gross

        e.Graphics.DrawString("TOTAL AMOUNT:" + Environment.NewLine + "DUE TO SCIPSI:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(600, 370)) 'gross
        e.Graphics.DrawString(frmBilling.txtScipsi.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(610, 409)) 'gross
    End Sub


    Private Sub Panel1_Paint(sender As Object, e As PaintEventArgs) Handles Panel1.Paint

    End Sub


End Class