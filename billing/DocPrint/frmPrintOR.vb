Imports System.Data
Imports System.Data.SqlClient
Imports System.Text

Public Class frmPrintOR


    Dim bill As String
    Dim netbill As String

    Private Sub frmPrintOR_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        'txtpartial.Text = SpellNumber(lblCash.Text)

        'If txtCheck.Text = "" Then
        '    PictureBox1.Visible = True
        'Else
        '    PictureBox1.Visible = False
        'End If
        'lbldate.Text = Date.Now.ToString("MM dd, yyyy")
        'With frmOR
        '    txtEmp.Text = frmBilling.txtName.Text
        'End With

        Try

            myCon.open()
            myCmd = New SqlCommand("SELECT ot_bill_no,ot_net,ot_or_no FROM tbl_orbill_trans WHERE ot_or_no = '" & frmor.txtOrno.Text & "'", myCon)
            myReader = myCmd.ExecuteReader
            If myReader.HasRows = True Then
                Do While myReader.Read()
                    billbox.Items.Add(myReader("ot_bill_no"))
                    netbox.Items.Add(myReader("ot_net"))
                Loop
            End If

            Dim billbuilder As New StringBuilder()
            For Each i As Object In billbox.Items
                billbuilder.AppendLine(i.ToString())
            Next
            bill = billbuilder.ToString()

            Dim netbillbuilder As New StringBuilder()
            For Each i As Object In netbox.Items
                netbillbuilder.AppendLine(i.ToString())
            Next
            netbill = netbillbuilder.ToString()
            myCmd.Dispose()
            myReader.Close()
        Catch ex As Exception
            MsgBox(ex.Message)
        Finally
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

            Dim myCmd2 = New SqlCommand("UPDATE tbl_or_trans SET or_indiprint='Y' WHERE or_num='" & frmor.txtOrno.Text & "'", myCon)
            myCmd2.ExecuteNonQuery()
            myCmd2.dispose()
            myCon.close()
        End Try

        Me.Dispose()
    End Sub

    Private Sub PrintDocument1_PrintPage(sender As Object, e As Printing.PrintPageEventArgs) Handles PrintDocument1.PrintPage
        Dim sysdate As Date = frmor.txtTime.Text
        Dim mydate As String
        If frmor.lbldatetrans.Text = "" Then
            mydate = sysdate.ToString("MM/dd/yyyy")
        Else
            mydate = frmor.lbldatetrans.Text
        End If
        e.Graphics.DrawString(mydate, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(605, 115)) 'date
        e.Graphics.DrawString(frmBilling.txtName.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(547, 288)) 'employee
        'e.Graphics.DrawString("BALANCE: " + frmOR.txtbal.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(510, 320)) 'discount
        e.Graphics.DrawString(lblclient.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(413, 134)) 'client
        e.Graphics.DrawString(txtCheck.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(200, 317)) 'chcke number

        e.Graphics.DrawString("BILL VAT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(130, 244)) 'chcke number
        e.Graphics.DrawString(lblVat.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(136, 257)) 'chcke number

        e.Graphics.DrawString("BILL AMT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(130, 270)) 'chcke number
        e.Graphics.DrawString(lblAmt.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(136, 283)) 'chcke number

        e.Graphics.DrawString("W/H TAX:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 244)) 'chcke number
        e.Graphics.DrawString(lblTax.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(230, 257)) 'chcke number

        'e.Graphics.DrawString("DISCOUNT:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 218)) 'chcke number
        'e.Graphics.DrawString(lblDiscount.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(230, 231)) 'chcke number

        e.Graphics.DrawString("TOTAL DUE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 270)) 'chcke number
        e.Graphics.DrawString(lblTotal.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(230, 283)) 'chcke number

        'e.Graphics.DrawString("CASH:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 270)) 'chcke number
        'e.Graphics.DrawString(lblCash.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(230, 283)) 'chcke number

        'e.Graphics.DrawString("CHANGE:", New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 270)) 'chcke number
        'e.Graphics.DrawString(lblChange.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(230, 283)) 'chcke number

        e.Graphics.DrawString(bill, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(120, 60)) 'chcke number
        e.Graphics.DrawString(netbill, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(220, 60)) 'chcke number

        'e.Graphics.DrawString(txtword.Text, New Font("Microsoft San Serif", 8), Brushes.Black, RectangleF(100, 30), New Point(413, 211)) 'chcke number
        e.Graphics.DrawString(lblval.Text, New Font("Microsoft San Serif", 10), Brushes.Black, New Point(670, 231)) 'chcke number

        'e.Graphics.DrawString(txtpartial.Text, New Font("Microsoft San Serif", 8), Brushes.Black, New Point(444, 259)) 'chcke number


        ' Create string to draw.
        Dim drawString As [String] = txtword.Text
        ' Create font and brush.
        Dim drawFont As New Font("Microsoft San Serif", 8)
        Dim drawBrush As New SolidBrush(Color.Black)
        ' Create rectangle for drawing.
        Dim x As Single = 413.0F
        Dim y As Single = 208.0F
        Dim width As Single = 330.0F
        Dim height As Single = 35.0F
        Dim drawRect As New RectangleF(x, y, width, height)

        Dim drawFormat As New StringFormat()
        drawFormat.Alignment = StringAlignment.Near
        ' Draw string to screen.
        e.Graphics.DrawString(drawString, drawFont, drawBrush, _
        drawRect, drawFormat)


        'Dim x1 As Single = 444.0F
        'Dim y1 As Single = 259.0F
        'Dim width1 As Single = 330.0F
        'Dim height1 As Single = 30.0F
        'Dim drawRect1 As New RectangleF(x1, y1, width1, height1)

        'Dim drawFormat1 As New StringFormat()
        'drawFormat1.Alignment = StringAlignment.Near
        '' Draw string to screen.
        'e.Graphics.DrawString(txtpartial.Text, New Font("Microsoft San Serif", 8), New SolidBrush(Color.Black), _
        'drawRect1, drawFormat1)

        ' FOR NEW OR FIELDS
        ' Dim drawString1 As [String] = lblAddress.Text
        ' Create font and brush.
        ' Dim drawFont1 As New Font("Microsoft San Serif", 8)
        ' Dim drawBrush1 As New SolidBrush(Color.Black)
        ' Create rectangle for drawing.
        ' Dim x1 As Single = 345.0F
        'Dim y1 As Single = 160.0F
        'Dim width1 As Single = 500.0F
        'Dim height1 As Single = 35.0F
        'Dim drawRect1 As New RectangleF(x1, y1, width, height)

        '  Dim drawFormat1 As New StringFormat()
        'drawFormat1.Alignment = StringAlignment.Near
        ' Draw string to screen.
        'e.Graphics.DrawString(drawString1, drawFont1, drawBrush1,
        'drawRect1, drawFormat1)

        e.Graphics.DrawString(frmor.lblAddress.Text, New Font("Microsoft San Serif", 9), Brushes.Black, New Point(320, 162))
        e.Graphics.DrawString(frmor.txtBstyle.Text, New Font("Microsoft San Serif", 9), Brushes.Black, New Point(390, 190))
        e.Graphics.DrawString(frmor.lblTin.Text, New Font("Microsoft San Serif", 9), Brushes.Black, New Point(610, 190))

    End Sub



    Private Sub dgOrItem_CellContentClick(sender As Object, e As DataGridViewCellEventArgs) Handles dgOrItem.CellContentClick

    End Sub

    Private Sub txtword_TextChanged(sender As Object, e As EventArgs) Handles txtword.TextChanged

    End Sub

    Private Sub lblBstyle_Click(sender As Object, e As EventArgs) Handles lblBstyle.Click

    End Sub

    Private Sub lblAddress_Click(sender As Object, e As EventArgs) Handles lblAddress.Click

    End Sub
End Class