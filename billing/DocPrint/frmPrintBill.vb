Imports System.Data
Imports System.Data.SqlClient
Imports System.Text

Public Class frmPrintBill
    Dim qty As String
    Dim unit As String
    Dim service As String
    Dim cargo As String
    Dim rate As String
    Dim gross As String
    Dim cargoname As String
    Private Sub frmPrintBill_Load(sender As Object, e As EventArgs) Handles MyBase.Load
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
            rate = ratebuilder.ToString()

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
        e.Graphics.DrawString(frmBilling.txtAccountno.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(70, 100)) 'account
        e.Graphics.DrawString(frmBilling.lblAccname.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(70, 120)) 'account
        e.Graphics.DrawString(frmBilling.txtVessel.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(80, 240)) 'vessel
        e.Graphics.DrawString(frmBilling.txtVoyage.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(300, 240)) 'voyage
        e.Graphics.DrawString("IN/OUT: " + frmBilling.cbType.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(600, 220)) 'type
        e.Graphics.DrawString(frmBilling.txtParticulars.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(340, 240)) 'particulars
        e.Graphics.DrawString(mydate, New Font("Microsoft San Serif", 9), Brushes.Black, New Point(645, 185)) 'date
        e.Graphics.DrawString(frmBilling.txtName.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(460, 550)) 'employee
        e.Graphics.DrawString(qty, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(10, 277)) 'qty
        e.Graphics.DrawString(unit, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(68, 277)) 'unit
        e.Graphics.DrawString(service, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(120, 277)) 'service
        e.Graphics.DrawString(cargo, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(430, 277)) 'cargo
        'e.Graphics.DrawString(cargoname, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(385, 277)) 'cargo name
        e.Graphics.DrawString(rate, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(570, 277)) 'rate
        e.Graphics.DrawString(gross, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(650, 277)) 'gross

        'e.Graphics.DrawString("TOTAL CHARGES:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(210, 461)) 'gross text
        'e.Graphics.DrawString(frmBilling.txtTotal.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 474)) 'gross
        e.Graphics.DrawString("PPA SHARE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(530, 410)) 'ppa share text
        e.Graphics.DrawString(frmBilling.txtLessppa.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(635, 410)) 'ppa share

        e.Graphics.DrawString("DISCOUNT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(530, 425)) 'total discount text
        e.Graphics.DrawString(frmBilling.txtDiscount.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(635, 425)) 'discount
        e.Graphics.DrawString("SCIPSI SHARE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(530, 440)) 'scipsi share text
        e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(635, 440)) 'scipsi share

        e.Graphics.DrawString("12% VAT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(530, 455)) 'vat txt
        e.Graphics.DrawString(frmBilling.txtVat.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(635, 455)) 'vat

        '  e.Graphics.DrawString("TOTAL AMOUNT:" + Environment.NewLine + "DUE TO SCIPSI:", New Font("Microsoft San Serif", 8), Brushes.Black, New Point(600, 461)) 'total amount text
        e.Graphics.DrawString(frmBilling.txtScipsi.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(635, 500)) 'total amount

        'UPDATED POSITION AND FIELDS
        e.Graphics.DrawString(frmBilling.txtScipsi.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 400)) 'total amount
        e.Graphics.DrawString(frmBilling.txtVat.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 415)) 'vat
        e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 430)) 'scipsi share
        e.Graphics.DrawString(frmBilling.txtDiscount.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 445)) 'discount
        e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 465)) 'scipsi share
        e.Graphics.DrawString(frmBilling.txtVat.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 480)) 'vat
        e.Graphics.DrawString(frmBilling.txtScipsi.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(415, 495)) 'total amount
        If (frmBilling.txtVat.Text <> "0.00") Then
            e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(210, 440)) 'scipsi share
        Else
            e.Graphics.DrawString(frmBilling.txtNet.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(210, 475)) 'scipsi share
        End If
        e.Graphics.DrawString(frmBilling.txtVat.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(210, 495)) 'vat


        'additional fields


        'Dim drawString As [String] = frmBilling.lblAddress.Text
        ' Create font and brush.
        ' Dim drawFont As New Font("Microsoft San Serif", 8)
        ' Dim drawBrush As New SolidBrush(Color.Black)
        ' Create rectangle for drawing.
        '  Dim x As Single = 70.0F
        ' Dim y As Single = 160.0F
        '  Dim width As Single = 300.0F
        'Dim height As Single = 35.0F
        ' Dim drawRect As New RectangleF(x, y, width, height)
        ' Dim drawFormat As New StringFormat()
        ' drawFormat.Alignment = StringAlignment.Near
        ' Draw string to screen.
        ' e.Graphics.DrawString(drawString, drawFont, drawBrush,
        ' drawRect, drawFormat)


        'e.Graphics.DrawString(frmBilling.lblAddress.Text, New Font("Microsoft San Serif", 7), Brushes.Black, New Point(70, 145)) 'address

        Dim drawString As [String] = frmBilling.lblAddress.Text
        ' Create font and brush.
        Dim drawFont As New Font("Microsoft San Serif", 8)
        Dim drawBrush As New SolidBrush(Color.Black)
        ' Create rectangle for drawing.
        Dim x As Single = 70.0F
        Dim y As Single = 145.0F
        Dim width As Single = 400.0F
        Dim height As Single = 35.0F
        Dim drawRect As New RectangleF(x, y, width, height)

        Dim drawFormat As New StringFormat()
        drawFormat.Alignment = StringAlignment.Near
        ' Draw string to screen.
        e.Graphics.DrawString(drawString, drawFont, drawBrush,
        drawRect, drawFormat)

        e.Graphics.DrawString(frmBilling.lblBstyle.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(70, 170)) 'bus style
        e.Graphics.DrawString(frmBilling.lblTin.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(70, 185)) 'tin no
    End Sub

End Class