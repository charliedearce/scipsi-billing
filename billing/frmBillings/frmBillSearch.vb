Imports System.Data
Imports System.Data.SqlClient
Public Class frmBillSearch

    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property



    Private Sub frmBillSearch_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown


        If (e.KeyCode = Keys.Enter) Then
            If txtSearch.Text = "" Then
                MsgBox("Please Enter BILL number.")
            Else
                Dim change As Integer = txtSearch.Text
                txtSearch.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))

                myCon.open()
                myCmd = New SqlCommand("SELECT * FROM tbl_bill_trans WHERE bt_bill_num ='" & txtSearch.Text & "'", myCon)
                myReader = myCmd.ExecuteReader
                If myReader.HasRows Then
                    If myReader.Read = True Then
                        With frmBilling
                            .txtBillno.Text = myReader("bt_bill_num")
                            .lblAccname.Text = myReader("bt_account")
                            .txtAccountno.Text = myReader("bt_acc_num")
                            .txtVessel.Text = myReader("bt_vessel")
                            .txtVoyage.Text = myReader("bt_voyage")
                            .txtParticulars.Text = myReader("bt_particulars")
                            .txtperiod.Text = myReader("bt_per")


                            If myReader("bt_route") = "D" Then
                                .cbRoute.Text = "DOMESTIC"
                            Else
                                .cbRoute.Text = "FOREIGN"
                            End If
                            If myReader("bt_type") = "I" Then
                                .cbType.Text = "IN"
                            Else
                                .cbType.Text = "OUT"
                            End If

                            If myReader("bt_status") = "TRUE" Then
                                frmBilling.txtNotice.Text = "This Bill # is already cancelled."
                            Else
                                frmBilling.txtNotice.Text = ""
                            End If
                            '.txtTotal.Text = myReader("bt_total")
                            '.txtLessppa.Text = myReader("bt_ppa")
                            '.txtVat.Text = myReader("bt_vat")
                            '.txtScipsi.Text = myReader("bt_scipsi")
                            '.txtctiw.Text = myReader("bt_htax")
                            Dim d As Date = myReader("bt_date")
                            .lbldatetrans.Text = d.ToString("MM/dd/yyyy")
                            .txtName.Text = myReader("bt_employee")
                            '.txtNet.Text = myReader("bt_net")
                            '.txtDiscount.Text = myReader("bt_disc")
                            If myReader("bt_ppa") <> 0 Then
                                .ppa.Checked = True
                            Else
                                .ppa.Checked = False
                            End If
                            If myReader("bt_vat") <> 0 Then
                                .vat.Checked = True
                            Else
                                .vat.Checked = False
                            End If

                            If myReader("bt_indiprint") = "N" And myReader("bt_status") = "FALSE" Then
                                frmBilling.btnEdit.Visible = True
                            Else
                                frmBilling.btnEdit.Visible = False
                            End If




                        End With

                        With frmBilling
                            .btnUpdate.Enabled = True
                            .btnPrint.Enabled = True
                            .btnAddItem.Enabled = False
                            .btnNewBill.Enabled = True
                            .btnAddItem.Enabled = False
                            .txtBillno.Enabled = False
                            .txtAccountno.Enabled = False
                            .txtParticulars.Enabled = False
                            .txtVoyage.Enabled = False
                            .txtVessel.Enabled = False
                            .cbRoute.Enabled = False
                            .cbType.Enabled = False
                            .ppa.Enabled = False
                            .vat.Enabled = False
                            ' .citw.Enabled = False
                            .btnDeleteItem.Enabled = False
                            .btnSave.Enabled = False
                            .lbldatetrans.Enabled = False
                        End With
                        myCmd.Dispose()
                        myReader.Close()


                    End If




                    Dim dt As New DataTable
                    Dim ds As New DataSet
                    ds.Tables.Add(dt)

                    Dim myCmd1 = New SqlCommand("SELECT it_id,it_qty,it_unit,it_service,it_cargo,it_rate,it_disc,it_gross,it_ppa,it_net,it_tax,it_scipsi FROM tbl_item_trans WHERE it_bill_num = '" & frmBilling.txtBillno.Text & "'", myCon)
                    Dim myAdapter1 = New SqlDataAdapter(myCmd1)
                    myAdapter1.Fill(dt)


                    frmBilling.dgItems.DataSource = dt.DefaultView
                    

                    myCmd1.Dispose()
                    myCon.close()

                    Dim a1 As Decimal
                    Dim a2 As Decimal
                    Dim a3 As Decimal
                    Dim a4 As Decimal
                    Dim a5 As Decimal
                    Dim a6 As Decimal

                    For index As Integer = 0 To frmBilling.dgItems.RowCount - 1
                        With frmBilling
                            a1 += Convert.ToDecimal(.dgItems.Rows(index).Cells(7).Value)
                            a2 += Convert.ToDecimal(.dgItems.Rows(index).Cells(8).Value)
                            a3 += Convert.ToDecimal(.dgItems.Rows(index).Cells(6).Value)
                            a4 += Convert.ToDecimal(.dgItems.Rows(index).Cells(9).Value)
                            a5 += Convert.ToDecimal(.dgItems.Rows(index).Cells(10).Value)
                            a6 += Convert.ToDecimal(.dgItems.Rows(index).Cells(11).Value)
                        End With
                    Next
                    With frmBilling
                        .txtTotal.Text = a1
                        .txtLessppa.Text = a2
                        .txtDiscount.Text = a3
                        .txtNet.Text = a4
                        .txtVat.Text = a5
                        .txtScipsi.Text = a6
                    End With
                    Me.Hide()
                    txtSearch.Text = ""

                Else

                    frmBilling.txtNotice.Text = "Bill Number not Found."
                    txtSearch.Text = ""
                    myCon.close()
                End If
            End If
        ElseIf (e.KeyCode = Keys.Escape) Then
            Me.Hide()
            txtSearch.Text = ""
            frmBilling.txtNotice.Text = ""
            frmBilling.txtSearchIndicator.Text = 0
        End If


    End Sub

    Private Sub frmBillSearch_Load(sender As Object, e As EventArgs) Handles MyBase.Load
        Me.KeyPreview = True
    End Sub

    Private Sub txtSearch_KeyPress(sender As Object, e As KeyPressEventArgs) Handles txtSearch.KeyPress
        If Asc(e.KeyChar) <> 8 Then
            If (Asc(e.KeyChar) < 48 Or Asc(e.KeyChar) > 57) Then
                e.Handled = True
            End If
        End If
    End Sub


    Private Sub txtSearch_TextChanged(sender As Object, e As EventArgs) Handles txtSearch.TextChanged

    End Sub
End Class