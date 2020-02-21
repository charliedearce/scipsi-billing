Imports System.Data
Imports System.Data.SqlClient



Public Class frmORSearchvb
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub txtORno_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtORno.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub

    Private Sub txtORno_TextChanged(sender As Object, e As EventArgs) Handles txtORno.TextChanged

    End Sub

    Private Sub frmORSearchvb_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown

        On Error Resume Next

        If (e.KeyCode = Keys.Enter) Then

            If txtORno.Text = "" Then
                MsgBox("Please Enter OR number.")
            Else

                Dim change As Integer = txtORno.Text
                txtORno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))


                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_or_trans WHERE or_num ='" & txtORno.Text & "'", myCon)
                myReader = myCmd.ExecuteReader
                If myReader.HasRows Then
                    If myReader.Read = True Then
                        With frmor

                            .lblAccname.Text = myReader("or_accname")
                            .txtAccountno.Text = myReader("or_acc_num")
                            .txtOrno.Text = myReader("or_num")
                            .txtRemarks.Text = myReader("or_remarks")
                            '.txtORVat.Text = myReader("or_bill_vat")
                            '.txtORBillAmount.Text = myReader("or_bill_amount")
                            '.txtHTax.Text = myReader("or_htax")
                            '.txtNetIncome.Text = myReader("or_net")
                            .txtCash.Text = myReader("or_cash")
                            .txtChange.Text = myReader("or_change")
                            .txtCheckno.Text = myReader("or_check_num")
                            .txtDiscount.Text = myReader("or_discount")
                            .lbldatetrans.Text = myReader("or_date")
                            .txtbank.Text = myReader("or_bank")
                            .txtbal.Text = myReader("or_balance")
                            .txtperiod.Text = myReader("or_period")
                            .lblBstyle.Text = myReader("or_bstyle")
                            .lblTin.Text = myReader("or_tin")
                            .lblAddress.Text = myReader("or_baddress")

                            If myReader("or_status") = "TRUE" Then
                                frmor.txtNotice.Text = "This OR # is already cancelled."
                            Else
                                frmor.txtNotice.Text = ""
                            End If

                            Dim d As Date = myReader("or_date")
                            .lbldatetrans.Text = d.ToString("MM/dd/yyyy")

                            If myReader("or_indiprint") = "N" And myReader("or_status") = "FALSE" Then
                                .btnEdit.Visible = True
                            Else
                                .btnEdit.Visible = False
                            End If


                            .txtbank.Enabled = False
                            .txtOrno.Enabled = False
                            .txtAccountno.Enabled = False
                            .txtRemarks.Enabled = False
                            .btnNewOR.Enabled = True
                            .btnPrintOr.Enabled = True
                            .btnAddOr.Enabled = False
                            .btnpartial.Enabled = False
                            .citw.Enabled = False
                            .btnDeposit.Enabled = False
                            .lbldatetrans.Enabled = False

                        End With
                        myCmd.Dispose()
                        myReader.Close()


                    End If






                    Dim dt As New DataTable
                    Dim ds As New DataSet
                    ds.Tables.Add(dt)

                    Dim myCmd1 = New SqlCommand("SELECT ot_id,ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_disc,ot_net FROM tbl_orbill_trans WHERE ot_or_no = '" & txtORno.Text & "'", myCon)
                    myAdapter = New SqlDataAdapter(myCmd1)
                    myAdapter.Fill(dt)



                    frmor.dgOrItems.DataSource = dt.DefaultView

                    myCmd1.Dispose()
                    myCon.close()
                    Me.Hide()
                    'frmOR.txtNotice.Text = ""
                    'txtORno.Text = ""
                    'With frmor
                    '    .lblBstyle.Text = ""
                    '    .lblAddress.Text = ""
                    '    .lblTin.Text = ""
                    'End With
                    'searchClientNewDetails(frmor.txtAccountno.Text)
                    Dim vat As Decimal
                    Dim billamount As Decimal
                    Dim htax As Decimal
                    Dim net As Decimal
                    For index As Integer = 0 To frmor.dgOrItems.RowCount - 1

                        vat += Convert.ToDecimal(frmor.dgOrItems.Rows(index).Cells(2).Value)
                        billamount += Convert.ToDecimal(frmor.dgOrItems.Rows(index).Cells(3).Value)
                        htax += Convert.ToDecimal(frmor.dgOrItems.Rows(index).Cells(4).Value)
                        net += Convert.ToDecimal(frmor.dgOrItems.Rows(index).Cells(6).Value)

                    Next

                    With frmor
                        .txtORVat.Text = vat
                        .txtORBillAmount.Text = billamount
                        .txtHTax.Text = htax
                        .txtNetIncome.Text = net
                    End With

                Else

                    frmor.txtNotice.Text = "OR Number not Found."
                    txtORno.Text = ""
                    myCon.close()

                End If
            End If

        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Dispose()
            txtORno.Text = ""
            frmor.txtNotice.Text = ""
            frmor.txtSearchIndicator.Text = 0
        End If


    End Sub

    Private Sub frmORSearchvb_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub
End Class