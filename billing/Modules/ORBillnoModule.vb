Imports System.Data
Imports System.Data.SqlClient
Module ORBillnoModule
    Public Sub orBillNo()
        Dim change As Integer = frmORBillno.txtORBillno.Text
        frmORBillno.txtORBillno.Text = String.Format("{0:0000000000}", change.ToString("0000000000"))
        myCon.open()
        Dim mycmd4 = New SqlCommand(String.Format("Select * FROM tbl_bill_trans WHERE bt_bill_num = '{0}'", frmORBillno.txtORBillno.Text), myCon)
        Dim myReader4 As SqlDataReader = mycmd4.ExecuteReader()
        If myReader4.HasRows Then
            'Dim acc As String
            If myReader4.Read Then
                If myReader4("bt_acc_num") <> frmor.txtAccountno.Text Then
                    frmor.txtNotice.Text = "Account Code not match."
                    myReader4.Close()
                    myCon.close()
                Else
                    myCon.close()
                    myCon.open()
                    Dim mycmd2 = New SqlCommand(String.Format("Select * FROM tbl_orbill_trans,tbl_or_trans WHERE (or_num = ot_or_no AND or_status = 'FALSE' AND ot_bill_no = '{0}')", frmORBillno.txtORBillno.Text), myCon)
                    Dim myReader1 As SqlDataReader = mycmd2.ExecuteReader()
                    If myReader1.HasRows Then
                        Dim num As String
                        If myReader1.Read Then
                            num = myReader1("ot_or_no")
                            myReader1.Close()
                        End If
                        frmor.txtNotice.Text = "This Bill number is already Billed out. OR#: " + num
                        myCon.close()
                    Else
                        Try
                            myCon.close()
                            myCon.open()
                            Dim setcitw As Decimal
                            Dim mycmd3 = New SqlCommand("SELECT ctiw FROM tbl_settings", myCon)
                            Dim myreader3 = mycmd3.ExecuteReader()
                            If myreader3.read Then
                                setcitw = myreader3("ctiw")
                            End If
                            myreader3.close()
                            myCmd = New SqlCommand(String.Format("SELECT * FROM tbl_bill_trans WHERE bt_bill_num = '{0}'", frmORBillno.txtORBillno.Text), myCon)
                            myReader = myCmd.ExecuteReader()

                            If myReader.HasRows Then
                                If myReader.Read Then


                                    If myReader("bt_status") = "TRUE" Then
                                        frmor.txtNotice.Text = "This Bill number is cancelled."
                                    Else
                                        Dim bill As String = myReader("bt_bill_num")
                                        Dim vat As Decimal = myReader("bt_vat")
                                        Dim scipsi As Decimal = myReader("bt_scipsi")
                                        Dim htax As Decimal
                                        If frmor.citw.Checked = True Then
                                            htax = ((scipsi - vat) * setcitw)
                                        Else
                                            htax = 0
                                        End If
                                        Dim disc As Decimal = myReader("bt_disc")
                                        Dim net As Decimal
                                        net = scipsi - htax
                                        myReader.Close()
                                        Dim mycmd1 = New SqlCommand(String.Format("INSERT INTO tbl_orbill_trans (ot_or_no,ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_disc,ot_net,ot_partial,ot_balance,ot_indipartial) VALUES ('{0}','{1}','{2}','{3}','{4}','{5}','{6}',0,0,'F')", frmor.txtOrno.Text, bill, vat, scipsi, htax, disc, net), myCon)
                                        mycmd1.ExecuteNonQuery()
                                        frmORBillno.Hide()
                                        frmORBillno.txtORBillno.Text = ""
                                        frmor.txtNotice.Text = ""
                                    End If
                                End If
                            End If
                        Catch ex As Exception
                            MsgBox(ex.Message)
                        Finally
                            myCmd.Dispose()
                            myCon.close()
                        End Try
                    End If
                End If
            End If
        Else
            myCon.close()
            frmor.txtNotice.Text = "Invalid Bill number! Please Try Again."
        End If
    End Sub

    Public Sub viewORItems()
        myCon.open()
        Dim dt As New DataTable
        Dim ds As New DataSet
        ds.Tables.Add(dt)

        myCmd = New SqlCommand(String.Format("SELECT ot_id,ot_bill_no,ot_bill_vat,ot_scipsi,ot_citw,ot_disc,ot_net FROM tbl_orbill_trans WHERE ot_or_no = '{0}'", frmor.txtOrno.Text), myCon)
        myAdapter = New SqlDataAdapter(myCmd)
        If myAdapter.Fill(dt) <> 0 Then
            With frmor
                .txtOrno.Enabled = False
                .btnDeleteOr.Enabled = True
                .btnCashtendered.Enabled = True
                .btnSearchOr.Enabled = False
                .btnBack.Enabled = False

            End With
            frmBilling.btnOut.Enabled = False
        Else
            With frmor
                If .btnNewOR.Enabled = True Then
                    .txtOrno.Enabled = False
                Else
                    .txtOrno.Enabled = True
                End If

                .btnBack.Enabled = True
                .btnDeleteOr.Enabled = False
                .btnCashtendered.Enabled = False
                .btnSearchOr.Enabled = True
                frmBilling.btnOut.Enabled = True
            End With
        End If

        frmor.dgOrItems.DataSource = dt.DefaultView

        If frmor.dgOrItems.RowCount <> 0 Then


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
            With frmor
                .txtORVat.Text = 0
                .txtORBillAmount.Text = 0
                .txtHTax.Text = 0
                .txtNetIncome.Text = 0
            End With

        End If





        myCmd.Dispose()
        myCon.close()

        If frmor.dgOrItems.Rows.Count <= 11 Then
            frmor.btnAddOr.Enabled = True
            frmor.btnpartial.Enabled = True
            ' frmOR.btnDeposit.Enabled = True
        End If
    End Sub
End Module
